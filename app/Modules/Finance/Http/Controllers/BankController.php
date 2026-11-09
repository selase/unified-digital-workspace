<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\ImportStatementRequest;
use App\Modules\Finance\Http\Requests\MatchTransactionRequest;
use App\Modules\Finance\Http\Requests\RecordTransactionRequest;
use App\Modules\Finance\Http\Requests\SaveBankAccountRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBankAccount;
use App\Modules\Finance\Models\FinBankTransaction;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Services\BankReconciliation;
use App\Modules\Finance\Services\BankStatementImporter;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BankController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, BankReconciliation $reconciliation, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $banks = FinBankAccount::query()->where('tenant_id', $tenantId)->with('account')->orderBy('name')->get();

        return view('finance::bank.index', [
            'banks' => $banks,
            'summaries' => $banks->mapWithKeys(fn (FinBankAccount $bank): array => [$bank->id => $reconciliation->summary($bank)]),
            'linkableAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true)
                ->whereNotIn('id', $banks->pluck('account_id'))->orderBy('code')->get(),
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function store(SaveBankAccountRequest $request): RedirectResponse
    {
        FinBankAccount::query()->create([
            ...$request->safe()->only(['name', 'account_id', 'institution', 'account_reference']),
            'tenant_id' => $this->tenantId($request),
            'opening_balance' => Money::toMinor($request->validated('opening_balance') ?? 0),
        ]);

        return redirect()->route('finance.bank.index')->with('success', 'Bank account added. Import a statement to start reconciling.');
    }

    public function show(Request $request, BankReconciliation $reconciliation, FinanceSettings $settings, string $bank): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = $this->find($tenantId, $bank)->load('account');
        $filter = $request->string('show')->toString();

        $transactions = FinBankTransaction::query()->where('bank_account_id', $model->id)->with(['journalLine.journal', 'matchedLines'])
            ->when($filter === '' || $filter === 'open', fn ($query) => $query->where('status', '!=', FinBankTransaction::STATUS_MATCHED))
            ->when($filter === 'matched', fn ($query) => $query->where('status', FinBankTransaction::STATUS_MATCHED))
            ->orderBy('date')->orderBy('id')->paginate(50)->withQueryString();

        $candidates = $transactions->getCollection()
            ->filter(fn (FinBankTransaction $transaction): bool => $transaction->status === FinBankTransaction::STATUS_UNMATCHED)
            ->mapWithKeys(fn (FinBankTransaction $transaction): array => [$transaction->id => $reconciliation->candidatesFor($transaction, $model)]);

        return view('finance::bank.show', [
            'bank' => $model,
            'summary' => $reconciliation->summary($model),
            'transactions' => $transactions,
            'candidates' => $candidates,
            'suggestions' => $reconciliation->suggestions($model),
            'outstanding' => $reconciliation->unmatchedLedgerLines($model)->with('journal')->get()->sortBy(fn ($line) => $line->journal->date),
            'expenseAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'expense')->where('is_active', true)->orderBy('code')->get(),
            'inflowAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->whereIn('type', ['income', 'liability', 'equity'])->where('is_active', true)->orderBy('code')->get(),
            'contacts' => FinContact::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'filter' => $filter === '' ? 'open' : $filter,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function import(ImportStatementRequest $request, BankStatementImporter $importer, string $bank): RedirectResponse
    {
        $model = $this->find($this->tenantId($request), $bank);

        try {
            $result = $importer->import($model, $request->file('statement')->getRealPath(), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        $message = $result['imported'].' line(s) imported, '.$result['skipped'].' already there.';
        $redirect = back()->with('success', $message);

        return $result['errors'] === [] ? $redirect : $redirect->with('error', 'Some rows were skipped: '.implode(' ', array_slice($result['errors'], 0, 5)));
    }

    public function autoMatch(Request $request, BankReconciliation $reconciliation, string $bank): RedirectResponse
    {
        $model = $this->find($this->allow($request, 'finance.bank.manage'), $bank);
        $count = $reconciliation->autoMatch($model);

        return back()->with('success', $count.' statement line(s) matched.');
    }

    public function match(MatchTransactionRequest $request, BankReconciliation $reconciliation, string $transaction): RedirectResponse
    {
        try {
            $reconciliation->match($this->transaction($request, $transaction), (int) $request->validated('journal_line_id'));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Matched.');
    }

    public function matchMany(Request $request, BankReconciliation $reconciliation, string $transaction): RedirectResponse
    {
        $this->allow($request, 'finance.bank.manage');
        $ids = collect((array) $request->input('journal_line_ids', []))->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->all();

        try {
            $reconciliation->matchMany($this->transaction($request, $transaction), $ids);
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Matched to '.count($ids).' ledger entries.');
    }

    public function unmatch(Request $request, BankReconciliation $reconciliation, string $transaction): RedirectResponse
    {
        $this->allow($request, 'finance.bank.manage');
        $reconciliation->unmatch($this->transaction($request, $transaction));

        return back()->with('success', 'Match removed.');
    }

    public function exclude(Request $request, BankReconciliation $reconciliation, string $transaction): RedirectResponse
    {
        $this->allow($request, 'finance.bank.manage');

        try {
            $reconciliation->exclude($this->transaction($request, $transaction));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Line excluded.');
    }

    public function restore(Request $request, BankReconciliation $reconciliation, string $transaction): RedirectResponse
    {
        $this->allow($request, 'finance.bank.manage');
        $reconciliation->restore($this->transaction($request, $transaction));

        return back()->with('success', 'Line restored.');
    }

    public function record(RecordTransactionRequest $request, BankReconciliation $reconciliation, string $transaction): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $contact = $request->validated('contact_id') ? FinContact::query()->where('tenant_id', $tenantId)->find((int) $request->validated('contact_id')) : null;

        try {
            $reconciliation->recordFromStatement($this->transaction($request, $transaction), (int) $request->validated('account_id'), $contact, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Booked to the ledger and matched.');
    }

    private function find(string $tenantId, string $uuid): FinBankAccount
    {
        return FinBankAccount::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }

    private function transaction(Request $request, string $uuid): FinBankTransaction
    {
        return FinBankTransaction::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $uuid)->firstOrFail();
    }
}
