<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\BillingCommunicationService;
use App\Services\Tenancy\BillingSuspensionService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

function makeIssuedInvoice(Tenant $tenant, string $dueAt, string $status = Invoice::STATUS_ISSUED): Invoice
{
    return Invoice::query()->create([
        'tenant_id' => $tenant->id, 'number' => 'INV-T-'.uniqid(), 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
        'due_at' => $dueAt, 'status' => $status, 'currency' => 'GHS', 'subtotal' => 600, 'tax_total' => 0, 'total' => 600,
    ]);
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('suspends the day after fourteen days past due and not before', function (): void {
    $tenant = Tenant::factory()->create();
    $invoice = makeIssuedInvoice($tenant, '2026-11-15');
    $service = app(BillingSuspensionService::class);

    expect($service->suspensionDate($invoice)->toDateString())->toBe('2026-11-30');

    foreach (['2026-11-29' => false, '2026-11-30' => true] as $day => $expected) {
        Carbon::setTestNow($day.' 10:00:00');
        Cache::flush();
        expect($service->isSuspended($tenant))->toBe($expected);
    }
});

it('does not suspend for paid, draft or void invoices', function (string $status): void {
    $tenant = Tenant::factory()->create();
    makeIssuedInvoice($tenant, '2026-01-01', $status);
    Cache::flush();

    expect(app(BillingSuspensionService::class)->isSuspended($tenant))->toBeFalse();
})->with([Invoice::STATUS_PAID, Invoice::STATUS_DRAFT, Invoice::STATUS_VOID]);

it('does not suspend when suspension is switched off', function (): void {
    config(['billing.suspension_enabled' => false]);
    $tenant = Tenant::factory()->create();
    makeIssuedInvoice($tenant, '2026-01-01');
    Cache::flush();

    expect(app(BillingSuspensionService::class)->isSuspended($tenant))->toBeFalse();
});

it('shows a neutral unavailable page to visitors of a suspended site', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'late-payer']);
    makeIssuedInvoice($tenant, now()->subDays(30)->toDateString());
    Cache::flush();
    app(TenantContext::class)->setTenant($tenant);

    $this->get('/login')->assertOk();
    $this->get('/some-public-page')
        ->assertStatus(503)
        ->assertSee('This site is temporarily unavailable and will be back as soon as possible.')
        ->assertDontSee('invoice', false)->assertDontSee('unpaid', false)->assertDontSee('payment', false);
});

it('sends a signed-in member of a suspended organisation to billing', function (): void {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    makeIssuedInvoice($tenant, now()->subDays(30)->toDateString());
    Cache::flush();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('billing.index'));
});

it('leaves an organisation with a recent unpaid invoice untouched', function (): void {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user);
    makeIssuedInvoice($tenant, now()->subDays(5)->toDateString());
    Cache::flush();

    $this->actingAs($user)->get('/some-public-page')->assertNotFound();
});

it('queues a reminder every other day up to the due date and warnings every other day after it', function (): void {
    $tenant = Tenant::factory()->create(['email' => 'billing@client.example']);
    $invoice = makeIssuedInvoice($tenant, '2026-11-15');
    $count = fn (): int => App\Models\BillingMessage::query()->where('invoice_id', $invoice->id)->count();
    $service = app(BillingCommunicationService::class);

    $expectedKinds = [
        '2026-11-03' => 'invoice_due', '2026-11-05' => 'invoice_due', '2026-11-15' => 'invoice_due',
        '2026-11-17' => 'invoice_overdue', '2026-11-29' => 'invoice_overdue',
    ];
    foreach ($expectedKinds as $day => $kind) {
        Carbon::setTestNow($day.' 08:00:00');
        $before = $count();
        $service->runDue();
        expect($count())->toBe($before + 1);
        expect(App\Models\BillingMessage::query()->where('invoice_id', $invoice->id)->latest('id')->value('kind'))->toBe($kind);
    }

    foreach (['2026-11-04', '2026-11-16', '2026-11-18'] as $quietDay) {
        Carbon::setTestNow($quietDay.' 08:00:00');
        $before = $count();
        $service->runDue();
        expect($count())->toBe($before);
    }
});

it('puts the suspension date and a warning in the overdue email', function (): void {
    $tenant = Tenant::factory()->create(['email' => 'billing@client.example']);
    $invoice = makeIssuedInvoice($tenant, '2026-11-15');
    Carbon::setTestNow('2026-11-17 08:00:00');
    app(BillingCommunicationService::class)->runDue();

    $payload = App\Models\BillingMessage::query()->where('invoice_id', $invoice->id)->where('kind', 'invoice_overdue')->firstOrFail()->payload;

    expect($payload['suspension_date'])->toBe('2026-11-30')->and($payload['days_overdue'])->toBe(2);
});

it('issues generated invoices automatically unless the tenant opts out', function (): void {
    Illuminate\Support\Facades\Mail::fake();
    $package = App\Models\Package::factory()->create(['price' => 100, 'price_ghs' => 100]);
    $auto = Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id, 'email' => 'auto@client.example']);
    $manual = Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id, 'email' => 'manual@client.example', 'meta' => ['billing_auto_issue' => false]]);

    $this->artisan('billing:generate-invoices', ['--month' => '2026-09'])->assertSuccessful();

    expect(Invoice::query()->where('tenant_id', $auto->id)->value('status'))->toBe(Invoice::STATUS_ISSUED)
        ->and(Invoice::query()->where('tenant_id', $manual->id)->value('status'))->toBe(Invoice::STATUS_DRAFT);
});
