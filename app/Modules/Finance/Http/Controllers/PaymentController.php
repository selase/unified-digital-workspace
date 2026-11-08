<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\RecordPaymentRequest;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Support\Money;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'finance.view');

        $openInvoices = FinInvoice::query()->where('tenant_id', $tenantId)
            ->where('type', FinInvoice::TYPE_INVOICE)
            ->whereIn('status', [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID])
            ->orderBy('due_date')->get();

        return view('finance::payments.index', [
            'payments' => FinPayment::query()->where('tenant_id', $tenantId)->with('contact')->latest('date')->latest('id')->paginate(25),
            'contacts' => FinContact::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get(),
            'openInvoices' => $openInvoices->groupBy('contact_id'),
            'depositAccounts' => FinAccount::query()->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_active', true)->orderBy('code')->get(),
            'preselectedInvoice' => $request->query('invoice'),
        ]);
    }

    public function store(RecordPaymentRequest $request, PaymentService $payments): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $contact = FinContact::query()->where('tenant_id', $tenantId)->findOrFail((int) $request->validated('contact_id'));

        try {
            $payments->record(
                $tenantId,
                $contact,
                Money::toMinor($request->validated('amount')),
                Carbon::parse($request->validated('date')),
                $request->allocationAmounts(),
                $request->safe()->only(['method', 'reference', 'deposit_account_id', 'notes']),
                $this->actorId($request),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('finance.payments.index')->with('success', 'Payment recorded.');
    }

    public function void(Request $request, PaymentService $payments, string $payment): RedirectResponse
    {
        $tenantId = $this->allow($request, 'finance.payments.manage');
        $model = FinPayment::query()->where('tenant_id', $tenantId)->where('uuid', $payment)->firstOrFail();

        try {
            $payments->void($model, $this->actorId($request));
        } catch (DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Payment voided and the invoices re-opened.');
    }
}
