<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Services\Payment\PaystackGateway;
use App\Services\Payment\StripeGateway;
use DomainException;

final class InvoiceCheckoutService
{
    public function __construct(private InvoicePaymentLinkService $links) {}

    public function availableFor(Invoice $invoice): bool
    {
        return match ($invoice->currency) {
            'GHS' => filled(config('services.paystack.secret_key')),
            'USD' => filled(config('services.stripe.secret')),
            default => false,
        };
    }

    public function checkout(Invoice $invoice): string
    {
        if (! $this->availableFor($invoice)) {
            throw new DomainException('Online payment is awaiting setup. Please contact billing support; do not send card details by email.');
        }
        if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE], true)) {
            throw new DomainException('This invoice cannot be paid.');
        }
        $amount = (int) round((float) $invoice->total * 100);
        $currency = mb_strtoupper((string) $invoice->currency);
        $tenant = $invoice->tenant;
        $email = $tenant?->meta['billing_email'] ?? $tenant?->email;
        if ($amount <= 0 || ! in_array($currency, ['GHS', 'USD'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('This invoice does not have valid payment details. Please contact billing support.');
        }
        $provider = $currency === 'GHS' ? 'paystack' : 'stripe';
        $metadata = [
            'application' => 'udworkspace',
            'invoice_id' => (string) $invoice->id,
            'tenant_id' => (string) $invoice->tenant_id,
            'type' => 'invoice_payment',
            'description' => 'Invoice #'.$invoice->number,
        ];
        $callback = $this->links->callbackUrl($provider);

        return $provider === 'paystack'
            ? app(PaystackGateway::class)->createInvoiceCheckout($email, $amount, $callback, $metadata)
            : app(StripeGateway::class)->createOneTimeCheckoutSession($email, $amount, $currency, $callback, $metadata);
    }
}
