<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\RecordBillPaymentRequest;
use App\Modules\Finance\Http\Requests\SaveBillRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBill;
use App\Modules\Finance\Models\FinBillPayment;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\BillService;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BillController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $status = $request->string('status')->toString();

        return view('finance::bills.index', [
            'bills' => FinBill::query()->where('tenant_id', $tenantId)->with('contact')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest('issue_date')->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
            'currency' => $settings->for($tenantId)->base_currency,
        ]);
    }

    public function create(Request $request, FinanceSettings $settings): View
    {
        $tenantId = $this->allow($request, 'finance.bills.manage');

        return view('finance::bills.form', $this->formData($tenantId, new FinBill(['status' => FinBill::STATUS_DRAFT]), $settings));
    }

    public function store(SaveBillRequest $request, BillService $bills): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        try {
            $bill = $bills->saveDraft($tenantId, $this->vendor($tenantId, (int) $request->validated('contact_id')), $this->billData($request), $this->billLines($request), null, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.bills.show', $bill->uuid)->with('success', 'Draft saved. Approve it to post it to the ledger.');
    }

    public function show(Request $request, FinanceSettings $settings, string $bill): View
    {
        $tenantId = $this->allow($request, 'finance.view');
        $model = $this->find($tenantId, $bill)->load(['contact', 'lines.account', 'payments.bill']);

        return view('finance::bills.show', [
            'bill' => $model,
            'currency' => $settings->for($tenantId)->base_currency,
            'paymentAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function edit(Request $request, FinanceSettings $settings, string $bill): View|RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bills.manage');
        $model = $this->find($tenantId, $bill);

        if ($model->status !== FinBill::STATUS_DRAFT) {
            return redirect()->route('finance.bills.show', $model->uuid)->with('error', 'Only draft bills can be edited.');
        }

        return view('finance::bills.form', $this->formData($tenantId, $model->load('lines'), $settings));
    }

    public function update(SaveBillRequest $request, BillService $bills, string $bill): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $model = $this->find($tenantId, $bill);

        try {
            $bills->saveDraft($tenantId, $this->vendor($tenantId, (int) $request->validated('contact_id')), $this->billData($request), $this->billLines($request), $model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.bills.show', $model->uuid)->with('success', 'Draft updated.');
    }

    public function approve(Request $request, BillService $bills, string $bill): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bills.manage');

        try {
            $approved = $bills->approve($this->find($tenantId, $bill), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Bill '.$approved->number.' approved and posted to the ledger.');
    }

    public function credit(Request $request, BillService $bills, string $bill): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bills.manage');

        try {
            $draft = $bills->createCredit($this->find($tenantId, $bill), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.bills.edit', $draft->uuid)->with('success', 'Credit note drafted. Reduce the amounts if only part is credited, then approve it.');
    }

    public function void(Request $request, BillService $bills, string $bill): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bills.manage');

        try {
            $bills->void($this->find($tenantId, $bill), $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Bill voided and its journal reversed.');
    }

    public function pay(RecordBillPaymentRequest $request, BillService $bills, string $bill): RedirectResponse
    {
        $tenantId = $this->tenantId($request);

        try {
            $bills->pay(
                $this->find($tenantId, $bill),
                Money::toMinor($request->validated('amount')),
                Carbon::parse($request->validated('date')),
                (int) $request->validated('paid_from_account_id'),
                $request->safe()->only(['method', 'reference', 'notes']),
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Payment recorded.');
    }

    public function voidPayment(Request $request, BillService $bills, string $payment): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.bills.manage');
        $model = FinBillPayment::query()->where('tenant_id', $tenantId)->where('uuid', $payment)->firstOrFail();

        try {
            $bills->voidPayment($model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Payment voided and the bill re-opened.');
    }

    private function find(string $tenantId, string $uuid): FinBill
    {
        return FinBill::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }

    private function vendor(string $tenantId, int $id): FinContact
    {
        return FinContact::query()->where('tenant_id', $tenantId)->where('is_vendor', true)->findOrFail($id);
    }

    /**
     * @return array{issue_date: string, due_date: string|null, vendor_reference: string|null, notes: string|null}
     */
    private function billData(SaveBillRequest $request): array
    {
        return [
            'issue_date' => (string) $request->validated('issue_date'),
            'due_date' => $request->validated('due_date'),
            'vendor_reference' => $request->validated('vendor_reference'),
            'notes' => $request->validated('notes'),
        ];
    }

    /**
     * @return list<array{description: string, amount: int, account_id: int, fund_id: int|null}>
     */
    private function billLines(SaveBillRequest $request): array
    {
        return collect((array) $request->validated('lines'))->map(fn (array $line): array => [
            'description' => (string) $line['description'],
            'amount' => Money::toMinor($line['amount']),
            'account_id' => (int) $line['account_id'],
            'fund_id' => isset($line['fund_id']) && $line['fund_id'] !== '' ? (int) $line['fund_id'] : null,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $tenantId, FinBill $bill, FinanceSettings $settings): array
    {
        return [
            'bill' => $bill,
            'currency' => $settings->for($tenantId)->base_currency,
            'vendors' => FinContact::query()->where('tenant_id', $tenantId)->where('is_vendor', true)->where('is_active', true)->orderBy('name')->get(),
            'expenseAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->whereIn('type', ['expense', 'asset'])->where('is_active', true)->orderBy('code')->get(),
            'funds' => FinFund::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
