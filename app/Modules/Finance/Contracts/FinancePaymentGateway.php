<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

/**
 * How a tenant collects money from its own customers. Paystack is the first implementation;
 * another gateway only has to implement this and be bound in the service provider.
 */
interface FinancePaymentGateway
{
    public function key(): string;

    public function isConfigured(string $tenantId): bool;

    /**
     * @param  array<string, string>  $metadata
     * @return array{url: string, reference: string}
     */
    public function initialize(string $tenantId, string $email, int $amountMinor, string $currency, string $callbackUrl, array $metadata): array;

    /**
     * `authorization` describes the card used, so a reusable one can be kept (with the donor's consent) for later charges.
     *
     * @return array{paid: bool, amount: int, currency: string, reference: string, metadata: array<string, mixed>, authorization: array{code: string|null, reusable: bool, last4: string|null, brand: string|null}}
     */
    public function verify(string $tenantId, string $reference): array;

    /**
     * Charges a card that was saved with consent. The reference makes a repeated request safe.
     *
     * @param  array<string, string>  $metadata
     * @return array{paid: bool, amount: int, reference: string, message: string}
     */
    public function chargeAuthorization(string $tenantId, string $email, int $amountMinor, string $currency, string $authorizationCode, string $reference, array $metadata): array;

    /**
     * Payouts from the gateway to the organisation's bank, newest first.
     *
     * @return list<array{id: string, settled_on: string, gross: int, fees: int, net: int, status: string}>
     */
    public function settlements(string $tenantId): array;

    public function isValidWebhookSignature(string $tenantId, string $payload, ?string $signature): bool;
}
