<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\InvoiceCheckoutService;
use App\Services\Billing\InvoicePaymentLinkService;
use App\Services\Billing\InvoiceReceiptService;
use App\Services\Billing\PaymentFulfillmentService;
use App\Services\Payment\PaystackGateway;
use App\Services\Payment\StripeGateway;
use App\Services\Tenancy\PdfInvoiceService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

final class InvoicePaymentPortalController extends Controller
{
    public function show(Invoice $invoice, InvoicePaymentLinkService $links): View
    {
        $this->ensureVisible($invoice);

        return view('billing.payments.show', [
            'invoice' => $invoice->load(['tenant', 'items']),
            'paymentAvailable' => app(InvoiceCheckoutService::class)->availableFor($invoice),
            'additionalPayments' => \App\Models\Transaction::query()->where('tenant_id', $invoice->tenant_id)
                ->where('meta->invoice_id', $invoice->id)->where('status', 'reconciliation_required')->get(),
            'payUrl' => $links->signedUrl($invoice, 'billing.payment.pay'),
            'invoiceUrl' => $links->signedUrl($invoice, 'billing.payment.invoice'),
            'receiptUrl' => $links->signedUrl($invoice, 'billing.payment.receipt'),
        ]);
    }

    public function pay(Invoice $invoice, InvoiceCheckoutService $checkout, InvoicePaymentLinkService $links): RedirectResponse
    {
        $this->ensureVisible($invoice);
        try {
            return redirect()->away($checkout->checkout($invoice));
        } catch (DomainException $exception) {
            return redirect($links->url($invoice))->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return redirect($links->url($invoice))->with('error', 'Unable to start checkout. Please try again later.');
        }
    }

    public function invoice(Invoice $invoice, PdfInvoiceService $pdf): Response
    {
        $this->ensureVisible($invoice);

        return $pdf->download($invoice);
    }

    public function receipt(Invoice $invoice, InvoiceReceiptService $receipts): Response
    {
        $this->ensureVisible($invoice);
        abort_unless($invoice->status === Invoice::STATUS_PAID && data_get($invoice->meta, 'receipt.number'), 404);

        return $receipts->download($invoice);
    }

    public function callback(Request $request, string $provider, PaymentFulfillmentService $fulfillment, InvoicePaymentLinkService $links): RedirectResponse
    {
        abort_unless(in_array($provider, ['paystack', 'stripe'], true), 404);
        $reference = $provider === 'paystack' ? $request->query('reference') : $request->query('session_id');
        abort_unless(is_string($reference) && preg_match('/^[A-Za-z0-9_.=-]{1,200}$/D', $reference), 400, 'Invalid payment reference.');
        try {
            $gateway = app($provider === 'paystack' ? PaystackGateway::class : StripeGateway::class);
            $result = $gateway->verifyTransaction($reference);
            abort_unless(in_array($result['status'] ?? '', ['success', 'succeeded', 'paid'], true), 422, 'Payment has not been confirmed.');
            $invoice = Invoice::query()->findOrFail(data_get($result, 'metadata.invoice_id'));
            $fulfillment->fulfillInvoicePayment($invoice, (string) ($result['transaction_id'] ?? ''), $provider,
                (int) ($result['amount'] ?? 0), (string) ($result['currency'] ?? ''), (array) ($result['metadata'] ?? []));
        } catch (DomainException $exception) {
            abort(422, 'Payment verification did not match this invoice.');
        }

        return redirect($links->url($invoice))->with('success', 'Payment confirmed. Your receipt is available below.');
    }

    private function ensureVisible(Invoice $invoice): void
    {
        abort_unless(in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE, Invoice::STATUS_PAID], true), 404);
    }
}
