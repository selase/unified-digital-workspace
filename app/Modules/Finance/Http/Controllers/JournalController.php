<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\SaveJournalRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class JournalController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::journals.index', [
            'journals' => FinJournal::query()->where('tenant_id', $tenantId)->latest('date')->latest('id')->paginate(25),
        ]);
    }

    public function create(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.journals.post');

        return view('finance::journals.create', [
            'accounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('code')->get(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(SaveJournalRequest $request, LedgerService $ledger): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $request->validated();

        $lines = collect($data['lines'])->map(fn (array $line): array => [
            'account_id' => (int) $line['account_id'],
            'debit' => Money::toMinor($line['debit'] ?? 0),
            'credit' => Money::toMinor($line['credit'] ?? 0),
            'fund_id' => $line['fund_id'] ?? null,
        ])->filter(fn (array $line): bool => $line['debit'] > 0 || $line['credit'] > 0)->values()->all();

        try {
            $journal = $ledger->post($tenantId, Carbon::parse($data['date']), $lines, $data['memo'] ?? null, 'manual', null, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.journals.show', $journal->uuid)->with('success', 'Journal posted.');
    }

    public function show(Request $request, string $journal): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        return view('finance::journals.show', [
            'journal' => FinJournal::query()->where('tenant_id', $tenantId)->where('uuid', $journal)->with('lines.account')->firstOrFail(),
        ]);
    }

    public function reverse(Request $request, LedgerService $ledger, string $journal): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.journals.post');
        $model = FinJournal::query()->where('tenant_id', $tenantId)->where('uuid', $journal)->firstOrFail();

        if ($model->source_type !== 'manual') {
            return back()->with('error', 'Journals created by invoices and payments are reversed by voiding the document.');
        }

        try {
            $reversal = $ledger->reverse($model, null, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.journals.show', $reversal->uuid)->with('success', 'Journal reversed.');
    }
}
