<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Services\FinancePdf;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\OnlinePaymentService;
use App\Modules\Finance\Support\ActivateTenant;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Customer-facing invoice page. Reached only through a signed link.
 */
final class PublicInvoiceController extends Controller
{
    public function show(string $tenant, string $invoice, OnlinePaymentService $online): View
    {
        $model = $this->load($tenant, $invoice);

        return view('finance::public.invoice', [
            'document' => $model,
            'settings' => app(FinanceSettings::class)->for($model->tenant_id),
            'canPay' => $online->canCollect($model),
            'payUrl' => $online->payActionUrl($model),
            'pdfUrl' => $online->pdfUrl($model),
        ]);
    }

    public function pdf(string $tenant, string $invoice, FinancePdf $pdf): \Illuminate\Http\Response
    {
        $model = $this->load($tenant, $invoice);

        return response($pdf->output($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($model).'"',
        ]);
    }

    public function pay(string $tenant, string $invoice, OnlinePaymentService $online): RedirectResponse
    {
        $model = $this->load($tenant, $invoice);

        try {
            return redirect()->away($online->checkout($model));
        } catch (DomainException $error) {
            return redirect($online->paymentUrl($model))->with('error', $error->getMessage());
        } catch (Throwable $error) {
            report($error);

            return redirect($online->paymentUrl($model))->with('error', 'Unable to start the payment. Please try again later.');
        }
    }

    public function callback(Request $request, string $tenant, string $invoice, OnlinePaymentService $online): RedirectResponse
    {
        $model = $this->load($tenant, $invoice);
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');

        try {
            $payment = $reference !== '' ? $online->recordVerified($tenant, $reference) : null;
        } catch (Throwable $error) {
            report($error);
            $payment = null;
        }

        return redirect($online->paymentUrl($model))->with(
            $payment !== null ? 'success' : 'error',
            $payment !== null ? 'Payment received. Thank you.' : 'We could not confirm the payment yet. If you were charged, it will appear shortly.',
        );
    }

    private function load(string $tenantId, string $uuid): FinInvoice
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        ActivateTenant::for($tenant);

        return FinInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('uuid', $uuid)
            ->whereNotIn('status', [FinInvoice::STATUS_DRAFT, FinInvoice::STATUS_VOID])
            ->with(['contact', 'lines'])
            ->firstOrFail();
    }
}
