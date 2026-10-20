<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use App\Models\MerchantTransaction;
use App\Models\TenantPaymentGateway;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Billing\PaymentFulfillmentService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);
    $this->tenant->update([
        'slug' => 'parity-tenant',
        'llm_topup_balance' => 1000,
    ]);
});

test('invoice payment fulfillment is strictly idempotent across duplicate executions', function () {
    $invoice = Invoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-TEST-001',
        'period_start' => now(),
        'period_end' => now()->addMonth(),
        'due_at' => now()->addDays(7),
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal' => 100.00,
        'tax_total' => 0.00,
        'total' => 100.00,
    ]);

    $service = app(PaymentFulfillmentService::class);

    // First fulfillment
    $service->fulfillInvoicePayment($invoice, 'tx_stripe_999', 'stripe');

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and(Transaction::where('tenant_id', $this->tenant->id)->where('provider_transaction_id', 'tx_stripe_999')->count())->toBe(1);

    // Second fulfillment (replayed webhook or callback)
    $service->fulfillInvoicePayment($invoice, 'tx_stripe_999', 'stripe');

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and(Transaction::where('tenant_id', $this->tenant->id)->where('provider_transaction_id', 'tx_stripe_999')->count())->toBe(1);
});

test('llm token purchase fulfillment is strictly idempotent and prevents double-crediting', function () {
    Config::set('llm.token_packs', [
        'test_pack' => [
            'name' => 'Test Pack',
            'tokens' => 500000,
            'price' => 5.00,
            'currency' => 'USD',
        ],
    ]);

    $service = app(PaymentFulfillmentService::class);

    $initialBalance = (int) DB::connection('landlord')->table('tenants')->where('id', $this->tenant->id)->value('llm_topup_balance');
    expect($initialBalance)->toBe(1000);

    // First fulfillment
    $result1 = $service->fulfillLlmTokenPurchase($this->tenant, 'test_pack', 'tx_llm_123', 'stripe');
    expect($result1['status'])->toBe('fulfilled');

    $afterFirst = (int) DB::connection('landlord')->table('tenants')->where('id', $this->tenant->id)->value('llm_topup_balance');
    expect($afterFirst)->toBe(501000);

    // Replay with identical transaction ID (e.g. duplicate webhook or user reloading callback)
    $result2 = $service->fulfillLlmTokenPurchase($this->tenant, 'test_pack', 'tx_llm_123', 'stripe');
    expect($result2['status'])->toBe('already_fulfilled');

    $afterSecond = (int) DB::connection('landlord')->table('tenants')->where('id', $this->tenant->id)->value('llm_topup_balance');
    expect($afterSecond)->toBe(501000)
        ->and(Transaction::where('tenant_id', $this->tenant->id)->where('provider_transaction_id', 'tx_llm_123')->count())->toBe(1);
});

test('callback then webhook replay fulfills invoice without duplicate transaction', function () {
    $invoice = Invoice::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'number' => 'INV-CB-WH-001',
        'period_start' => now(),
        'period_end' => now()->addMonth(),
        'due_at' => now()->addDays(7),
        'status' => Invoice::STATUS_ISSUED,
        'currency' => 'USD',
        'subtotal' => 50.00,
        'tax_total' => 0.00,
        'total' => 50.00,
    ]);

    // 1. Mock PaymentGateway for CallbackController
    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('verifyTransaction')
        ->with('session_test_cb')
        ->andReturn([
            'status' => 'succeeded',
            'transaction_id' => 'tx_cb_then_wh_111',
            'amount' => 5000,
            'currency' => 'USD',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'tenant_id' => $this->tenant->id,
            ],
        ]);
    app()->instance(PaymentGateway::class, $mockGateway);

    // Act as user and hit callback
    $this->actingAs($this->user)
        ->get(route('billing.callback', ['session_id' => 'session_test_cb']))
        ->assertRedirect(app(App\Services\Billing\InvoicePaymentLinkService::class)->url($invoice));

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and(Transaction::where('tenant_id', $this->tenant->id)->count())->toBe(1);

    // 2. Now simulate webhook arrival for the same invoice
    $service = app(PaymentFulfillmentService::class);
    $service->fulfillInvoicePayment($invoice, 'tx_cb_then_wh_111', 'stripe');

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and(Transaction::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('merchant paystack webhook rejects bad signatures and accepts valid signatures idempotently', function () {
    TenantPaymentGateway::create([
        'tenant_id' => $this->tenant->id,
        'provider' => 'paystack',
        'api_key_encrypted' => 'sk_merchant_secret_123',
        'is_active' => true,
    ]);

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'merchant_ref_999',
            'amount' => 15000,
            'currency' => 'GHS',
            'customer' => ['email' => 'client@parity.test', 'first_name' => 'Kofi', 'last_name' => 'Mensah'],
        ],
    ]);

    // Bad signature -> 400
    $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid_sha_signature',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect(MerchantTransaction::where('provider_transaction_id', 'merchant_ref_999')->exists())->toBeFalse();

    // Valid signature -> 200
    $validSignature = hash_hmac('sha512', $payload, 'sk_merchant_secret_123');
    $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $validSignature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect(MerchantTransaction::where('provider_transaction_id', 'merchant_ref_999')->count())->toBe(1);

    // Duplicate webhook with identical valid signature -> 200 (idempotent, no duplicate transaction)
    $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $validSignature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect(MerchantTransaction::where('provider_transaction_id', 'merchant_ref_999')->count())->toBe(1);
});
