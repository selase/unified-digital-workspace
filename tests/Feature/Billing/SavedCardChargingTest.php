<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantPaymentMethod;
use App\Services\Billing\CardOnFileService;
use Illuminate\Support\Facades\Http;

function dueInvoice(Tenant $tenant, array $overrides = []): Invoice
{
    return Invoice::query()->create([
        'tenant_id' => $tenant->id, 'number' => 'INV-C-'.uniqid(), 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
        'due_at' => now()->subDay()->toDateString(), 'status' => Invoice::STATUS_ISSUED, 'currency' => 'GHS',
        'subtotal' => 600, 'tax_total' => 0, 'total' => 600, ...$overrides,
    ]);
}

function savedCard(Tenant $tenant): TenantPaymentMethod
{
    return TenantPaymentMethod::create([
        'tenant_id' => $tenant->id, 'authorization_code' => 'AUTH_secret123', 'signature' => 'SIG_'.uniqid(), 'email' => 'pay@client.example',
        'brand' => 'visa', 'last4' => '4081', 'consented_at' => now()->subWeek(), 'consent_version' => 'v1',
    ]);
}

function verifiedPayment(Invoice $invoice, array $overrides = []): array
{
    return [
        'status' => 'success', 'amount' => 60000, 'currency' => 'GHS', 'transaction_id' => 998877, 'customer_email' => 'pay@client.example',
        'metadata' => ['application' => 'udworkspace', 'invoice_id' => $invoice->id, 'save_card' => '1', 'consent_at' => now()->toIso8601String(), 'consent_version' => 'v1'],
        'authorization' => ['reusable' => true, 'channel' => 'card', 'authorization_code' => 'AUTH_new', 'signature' => 'SIG_new', 'brand' => 'mastercard', 'last4' => '1234'],
        ...$overrides,
    ];
}

it('does nothing while the feature is switched off', function (): void {
    config(['billing.card_autocharge_enabled' => false]);
    $tenant = Tenant::factory()->create();
    savedCard($tenant);
    $invoice = dueInvoice($tenant);
    Http::fake();

    expect(app(CardOnFileService::class)->chargeDueInvoices())->toBe(0);
    Http::assertNothingSent();
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
    expect(app(CardOnFileService::class)->captureFromPayment($invoice, verifiedPayment($invoice)))->toBeNull();
});

it('saves a reusable card only when the customer consented at checkout', function (): void {
    config(['billing.card_autocharge_enabled' => true]);
    $tenant = Tenant::factory()->create();
    $invoice = dueInvoice($tenant);
    $service = app(CardOnFileService::class);

    expect($service->captureFromPayment($invoice, verifiedPayment($invoice, ['metadata' => ['invoice_id' => $invoice->id]])))->toBeNull()
        ->and($service->captureFromPayment($invoice, verifiedPayment($invoice, ['authorization' => ['reusable' => false, 'channel' => 'card']])))->toBeNull();

    $method = $service->captureFromPayment($invoice, verifiedPayment($invoice));

    expect($method->last4)->toBe('1234')->and($method->description())->toBe('Mastercard card ending 1234')
        ->and($method->authorization_code)->toBe('AUTH_new')
        ->and(TenantPaymentMethod::query()->getConnection()->table('tenant_payment_methods')->value('authorization_code'))->not->toContain('AUTH_new');
});

it('charges the saved card on the due date, marks the invoice paid and does not repeat', function (): void {
    config(['billing.card_autocharge_enabled' => true]);
    $tenant = Tenant::factory()->create();
    savedCard($tenant);
    $invoice = dueInvoice($tenant);
    Http::fake([
        '*/transaction/charge_authorization' => Http::response(['data' => ['status' => 'success']]),
        '*/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'amount' => 60000, 'currency' => 'GHS', 'id' => 55001,
            'metadata' => ['application' => 'udworkspace', 'invoice_id' => $invoice->id, 'tenant_id' => $tenant->id],
        ]]),
    ]);

    expect(app(CardOnFileService::class)->chargeDueInvoices())->toBe(1);
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);

    expect(app(CardOnFileService::class)->chargeDueInvoices())->toBe(0);
});

it('records a declined charge, tries once a day and stops after three attempts', function (): void {
    config(['billing.card_autocharge_enabled' => true]);
    $tenant = Tenant::factory()->create();
    savedCard($tenant);
    $invoice = dueInvoice($tenant);
    Http::fake(['*/transaction/charge_authorization' => Http::response(['data' => ['status' => 'failed', 'gateway_response' => 'Insufficient Funds']])]);
    $service = app(CardOnFileService::class);

    expect($service->chargeDueInvoices())->toBe(0);
    expect(data_get($invoice->fresh()->meta, 'autocharge.last_error'))->toBe('Insufficient Funds')
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);

    $service->chargeDueInvoices();
    Http::assertSentCount(1);

    foreach ([1, 2] as $day) {
        $this->travel($day)->days();
        $service->chargeDueInvoices();
    }
    $this->travel(5)->days();
    $service->chargeDueInvoices();

    Http::assertSentCount(3);
    expect(data_get($invoice->fresh()->meta, 'autocharge.attempts'))->toBe(3);
});

it('never charges an invoice that is not yet due or has no saved card', function (): void {
    config(['billing.card_autocharge_enabled' => true]);
    $withCard = Tenant::factory()->create();
    savedCard($withCard);
    dueInvoice($withCard, ['due_at' => now()->addDays(3)->toDateString()]);
    $noCard = Tenant::factory()->create();
    dueInvoice($noCard);
    Http::fake();

    expect(app(CardOnFileService::class)->chargeDueInvoices())->toBe(0);
    Http::assertNothingSent();
});

it('lets the customer remove the saved card', function (): void {
    config(['billing.card_autocharge_enabled' => true]);
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    $method = savedCard($tenant);
    dueInvoice($tenant);
    Http::fake();

    app(CardOnFileService::class)->remove($method);

    expect(app(CardOnFileService::class)->methodFor($tenant))->toBeNull()
        ->and(app(CardOnFileService::class)->chargeDueInvoices())->toBe(0);
});

it('offers the consent box on the payment page only when the feature is on, and sends the consent with checkout', function (): void {
    $tenant = Tenant::factory()->create(['email' => 'pay@client.example']);
    config(['services.paystack.secret_key' => 'sk_test_x']);
    $invoice = dueInvoice($tenant);
    $url = app(App\Services\Billing\InvoicePaymentLinkService::class)->url($invoice);

    config(['billing.card_autocharge_enabled' => false]);
    $this->get($url)->assertOk()->assertDontSee('Save my card');

    config(['billing.card_autocharge_enabled' => true]);
    $this->get($url)->assertOk()->assertSee('Save my card and charge it automatically');

    Http::fake(['*/transaction/initialize' => Http::response(['data' => ['reference' => 'ref_1', 'access_code' => 'ac_1']])]);
    app(App\Services\Billing\InvoiceCheckoutService::class)->checkout($invoice, true);
    Http::assertSent(fn ($request) => data_get($request->data(), 'metadata.save_card') === '1' && data_get($request->data(), 'metadata.consent_version') === 'v1');
});
