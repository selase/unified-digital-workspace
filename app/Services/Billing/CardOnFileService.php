<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantPaymentMethod;
use App\Services\Payment\PaystackGateway;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CardOnFileService
{
    public const CONSENT_VERSION = 'v1';

    public const MAX_ATTEMPTS = 3;

    public function __construct(private PaystackGateway $gateway, private PaymentFulfillmentService $fulfillment) {}

    public function enabled(): bool
    {
        return (bool) config('billing.card_autocharge_enabled');
    }

    public function methodFor(Tenant $tenant): ?TenantPaymentMethod
    {
        return TenantPaymentMethod::query()->where('tenant_id', $tenant->id)->whereNull('removed_at')->latest()->first();
    }

    /**
     * Saves the card used for a verified payment, only when the customer ticked
     * the consent box at checkout and Paystack says the card can be reused.
     *
     * @param  array<string, mixed>  $verified  result of PaystackGateway::verifyTransaction
     */
    public function captureFromPayment(Invoice $invoice, array $verified): ?TenantPaymentMethod
    {
        $authorization = (array) ($verified['authorization'] ?? []);

        if (! $this->enabled()
            || data_get($verified, 'metadata.save_card') !== '1'
            || ($authorization['reusable'] ?? false) !== true
            || ($authorization['channel'] ?? '') !== 'card'
            || ! filled($authorization['authorization_code'] ?? null)
            || ! filled($authorization['signature'] ?? null)
            || ! filled($verified['customer_email'] ?? null)) {
            return null;
        }

        return TenantPaymentMethod::query()->updateOrCreate(
            ['tenant_id' => $invoice->tenant_id, 'signature' => (string) $authorization['signature']],
            [
                'authorization_code' => (string) $authorization['authorization_code'],
                'email' => (string) $verified['customer_email'],
                'brand' => $authorization['brand'] ?? null,
                'last4' => $authorization['last4'] ?? null,
                'exp_month' => $authorization['exp_month'] ?? null,
                'exp_year' => $authorization['exp_year'] ?? null,
                'consented_at' => Carbon::parse((string) data_get($verified, 'metadata.consent_at', now())),
                'consent_version' => (string) data_get($verified, 'metadata.consent_version', self::CONSENT_VERSION),
                'removed_at' => null,
            ],
        );
    }

    /** @param array<string, mixed> $verified */
    public function captureQuietly(Invoice $invoice, array $verified): void
    {
        try {
            $this->captureFromPayment($invoice, $verified);
        } catch (Throwable $error) {
            report($error);
        }
    }

    public function remove(TenantPaymentMethod $method): void
    {
        $method->update(['removed_at' => now()]);
    }

    /**
     * Charges the saved card for every invoice that is due, unpaid and not yet
     * attempted today. Returns the number of invoices paid.
     */
    public function chargeDueInvoices(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $paid = 0;
        $invoices = Invoice::query()->with('tenant')
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE])
            ->where('currency', 'GHS')->where('total', '>', 0)
            ->whereDate('due_at', '<=', today())
            ->get();

        foreach ($invoices as $invoice) {
            $attempts = (int) data_get($invoice->meta, 'autocharge.attempts', 0);
            $lastAttempt = data_get($invoice->meta, 'autocharge.last_attempt_at');
            $method = $invoice->tenant !== null ? $this->methodFor($invoice->tenant) : null;

            if ($method === null || $attempts >= self::MAX_ATTEMPTS || ($lastAttempt !== null && Carbon::parse($lastAttempt)->isToday())) {
                continue;
            }

            $paid += $this->chargeOne($invoice, $method, $attempts) ? 1 : 0;
        }

        return $paid;
    }

    private function chargeOne(Invoice $invoice, TenantPaymentMethod $method, int $attempts): bool
    {
        $attempt = $attempts + 1;
        $reference = 'udw-auto-'.$invoice->id.'-'.$attempt;
        $metadata = ['application' => 'udworkspace', 'invoice_id' => (string) $invoice->id, 'tenant_id' => (string) $invoice->tenant_id, 'type' => 'invoice_payment', 'auto_charge' => '1'];

        try {
            $charge = $this->gateway->chargeSavedCard($method->email, (int) round((float) $invoice->total * 100), $method->authorization_code, $reference, $metadata);
            if ($charge['status'] !== 'success') {
                return $this->recordFailure($invoice, $attempt, $charge['message']);
            }

            $verified = $this->gateway->verifyTransaction($reference);
            if (($verified['status'] ?? '') !== 'success') {
                return $this->recordFailure($invoice, $attempt, 'Charge could not be verified.');
            }

            $this->fulfillment->fulfillInvoicePayment($invoice, (string) $verified['transaction_id'], 'paystack',
                (int) $verified['amount'], (string) $verified['currency'], (array) $verified['metadata']);
            $method->update(['updated_at' => now()]);

            return true;
        } catch (DomainException $error) {
            report($error);

            return $this->recordFailure($invoice, $attempt, 'The charge did not match the invoice and needs a manual check.');
        } catch (Throwable $error) {
            report($error);

            return $this->recordFailure($invoice, $attempt, 'The payment provider could not be reached.');
        }
    }

    private function recordFailure(Invoice $invoice, int $attempt, string $reason): false
    {
        $invoice->update(['meta' => [...($invoice->meta ?? []), 'autocharge' => ['attempts' => $attempt, 'last_attempt_at' => now()->toIso8601String(), 'last_error' => $reason]]]);
        Log::warning('Saved-card charge failed', ['invoice' => $invoice->number, 'attempt' => $attempt, 'reason' => $reason]);

        return false;
    }
}
