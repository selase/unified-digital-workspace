<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\Tenant;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Models\FinPayment;
use App\Modules\Finance\Support\ActivateTenant;
use DomainException;

/**
 * Customer-facing payment links and the verified recording of a gateway payment.
 * Money is only recorded after the gateway confirms it, whether the customer comes
 * back to the callback or the webhook arrives first.
 */
final class OnlinePaymentService
{
    public function __construct(
        private readonly FinancePaymentGateway $gateway,
        private readonly PaymentService $payments,
    ) {}

    public function canCollect(FinInvoice $invoice): bool
    {
        return $invoice->type === FinInvoice::TYPE_INVOICE
            && in_array($invoice->status, [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID], true)
            && $invoice->balanceDue() > 0
            && $this->gateway->isConfigured($invoice->tenant_id);
    }

    public function paymentUrl(FinInvoice $invoice): string
    {
        return $this->signed('finance.public.invoice', $invoice);
    }

    public function pdfUrl(FinInvoice $invoice): string
    {
        return $this->signed('finance.public.pdf', $invoice);
    }

    public function payActionUrl(FinInvoice $invoice): string
    {
        return $this->signed('finance.public.pay', $invoice);
    }

    /** Starts a checkout and returns the gateway URL the customer is sent to. */
    public function checkout(FinInvoice $invoice): string
    {
        $invoice->loadMissing('contact');

        if (! $this->canCollect($invoice)) {
            throw new DomainException('This invoice cannot be paid online.');
        }

        $email = $invoice->contact->email;
        if (! is_string($email) || $email === '') {
            throw new DomainException('This customer has no email address on file, so online payment is unavailable.');
        }

        $session = $this->gateway->initialize(
            $invoice->tenant_id,
            $email,
            $invoice->balanceDue(),
            $invoice->currency,
            $this->signed('finance.public.callback', $invoice),
            [
                'type' => 'finance_invoice',
                'tenant_id' => $invoice->tenant_id,
                'finance_invoice_uuid' => $invoice->uuid,
            ],
        );

        return $session['url'];
    }

    /**
     * Confirms the payment with the gateway and records it once. Returns null when the
     * gateway does not report a successful payment for this tenant's invoice.
     */
    public function recordVerified(string $tenantId, string $reference): ?FinPayment
    {
        $result = $this->gateway->verify($tenantId, $reference);
        $metadata = $result['metadata'];

        if (! $result['paid'] || ($metadata['type'] ?? null) !== 'finance_invoice' || ($metadata['tenant_id'] ?? null) !== $tenantId) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            return null;
        }
        ActivateTenant::for($tenant);

        $invoice = FinInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('uuid', (string) ($metadata['finance_invoice_uuid'] ?? ''))
            ->with('contact')
            ->first();

        if (! $invoice instanceof FinInvoice || $invoice->currency !== $result['currency'] || $result['amount'] <= 0) {
            return null;
        }

        $allocation = min($result['amount'], $invoice->balanceDue());

        return $this->payments->record(
            $tenantId,
            $invoice->contact,
            $result['amount'],
            today(),
            $allocation > 0 && in_array($invoice->status, [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID], true) ? [$invoice->id => $allocation] : [],
            [
                'method' => 'online',
                'gateway' => $this->gateway->key(),
                'gateway_reference' => $result['reference'],
                'reference' => $result['reference'],
            ],
        );
    }

    private function signed(string $route, FinInvoice $invoice): string
    {
        $generator = clone app('url');
        $root = mb_rtrim((string) config('app.url'), '/');
        $generator->forceRootUrl($root);
        $generator->forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');

        return $generator->temporarySignedRoute($route, now()->addDays(60), [
            'tenant' => $invoice->tenant_id,
            'invoice' => $invoice->uuid,
        ]);
    }
}
