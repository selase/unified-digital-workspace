<?php

declare(strict_types=1);

use App\Models\MerchantTransaction;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    TenantPaymentGateway::query()->create([
        'tenant_id' => $this->tenant->id,
        'provider' => 'paystack',
        'api_key_encrypted' => 'sk_test_secret',
        'is_active' => true,
    ]);
    $this->payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_123',
            'amount' => 5000,
            'currency' => 'GHS',
            'customer' => ['email' => 'a@b.test', 'first_name' => 'A', 'last_name' => 'B'],
        ],
    ]);
});

test('paystack webhook accepts a valid signature', function () {
    $signature = hash_hmac('sha512', $this->payload, 'sk_test_secret');

    $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $this->payload)->assertOk();

    expect(MerchantTransaction::query()->where('provider_transaction_id', 'ref_123')->exists())->toBeTrue();
});

test('paystack webhook rejects a bad or missing signature', function () {
    $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => 'deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], $this->payload)->assertStatus(400);

    $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $this->payload)->assertStatus(400);

    expect(MerchantTransaction::query()->count())->toBe(0);
});
