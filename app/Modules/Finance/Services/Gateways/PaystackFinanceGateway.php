<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services\Gateways;

use App\Models\TenantPaymentGateway;
use App\Modules\Finance\Contracts\FinancePaymentGateway;
use DomainException;
use Illuminate\Support\Facades\Http;

/**
 * Uses the tenant's own Paystack keys from TenantPaymentGateway. For Purpledot these are the
 * same keys as the UDW platform account, so Finance events also reach the platform webhook,
 * which forwards them to FinancePaymentRecorder.
 */
final class PaystackFinanceGateway implements FinancePaymentGateway
{
    private const BASE_URL = 'https://api.paystack.co';

    public function key(): string
    {
        return 'paystack';
    }

    public function isConfigured(string $tenantId): bool
    {
        return $this->secret($tenantId) !== null;
    }

    public function initialize(string $tenantId, string $email, int $amountMinor, string $currency, string $callbackUrl, array $metadata): array
    {
        $response = Http::withToken($this->requireSecret($tenantId))->timeout(20)->post(self::BASE_URL.'/transaction/initialize', [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ])->throw();

        $url = $response->json('data.authorization_url');
        $reference = $response->json('data.reference');

        if (! is_string($url) || ! is_string($reference) || ! preg_match('/^[A-Za-z0-9_.=-]{1,200}$/D', $reference)) {
            throw new DomainException('Unable to start the payment. Please try again later.');
        }

        return ['url' => $url, 'reference' => $reference];
    }

    public function verify(string $tenantId, string $reference): array
    {
        $response = Http::withToken($this->requireSecret($tenantId))->timeout(20)
            ->get(self::BASE_URL.'/transaction/verify/'.rawurlencode($reference))->throw();

        return [
            'paid' => $response->json('data.status') === 'success',
            'amount' => (int) $response->json('data.amount', 0),
            'currency' => mb_strtoupper((string) $response->json('data.currency', '')),
            'reference' => (string) $response->json('data.reference', $reference),
            'metadata' => (array) $response->json('data.metadata', []),
        ];
    }

    public function isValidWebhookSignature(string $tenantId, string $payload, ?string $signature): bool
    {
        $secret = $this->secret($tenantId);

        return $secret !== null && $signature !== null && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function requireSecret(string $tenantId): string
    {
        return $this->secret($tenantId) ?? throw new DomainException('Online payments are not set up for this organisation.');
    }

    private function secret(string $tenantId): ?string
    {
        $gateway = TenantPaymentGateway::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();

        $secret = $gateway?->api_key_encrypted;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
