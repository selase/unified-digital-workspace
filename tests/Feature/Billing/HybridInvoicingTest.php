<?php

declare(strict_types=1);

use App\Enum\UsageMetric;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use App\Models\TenantBillingMonthlyUser;
use App\Models\UsageRollup;
use App\Services\Tenancy\BillingAgreementService;
use App\Services\Tenancy\InvoicingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 00:00:00'));
    $this->tenant = Tenant::factory()->create(['country' => 'GH']);
});

it('keeps legacy agreements entirely unchanged without an explicit adoption', function (): void {
    $package = Package::factory()->create(['price' => 10, 'price_ghs' => 100]);
    $this->tenant->update(['package_id' => $package->id]);
    expect(app(BillingAgreementService::class)->activeFor($this->tenant))->toBeNull();
    $this->travelTo(Carbon::parse('2026-11-01'));
    $invoice = app(InvoicingService::class)->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect((float) $invoice->subtotal)->toBe(100.0)->and((string) $this->tenant->fresh()->package_id)->toBe((string) $package->id);
});

it('invoices frozen website prices and setup once without extra charges for included usage', function (): void {
    $agreement = app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, true, now());
    config(['usage-pricing.hybrid.website_plans.essentials.GHS' => 9999]);
    foreach ([UsageMetric::STORAGE_BYTES, UsageMetric::DB_BYTES, UsageMetric::USER_ACTIVE_MONTHLY, UsageMetric::REQUEST_COUNT] as $metric) {
        UsageRollup::create(['tenant_id' => $this->tenant->id, 'period' => 'day', 'period_start' => now(), 'metric' => $metric, 'dimensions' => [], 'value' => 500]);
    }
    $this->travelTo(Carbon::parse('2026-11-01'));
    $service = app(InvoicingService::class);
    $invoice = $service->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect((float) $invoice->subtotal)->toBe(800.0)->and($invoice->currency)->toBe('GHS')
        ->and($invoice->meta['billing_agreement_id'])->toBe($agreement->id)
        ->and($service->generate($this->tenant, $invoice->period_start, $invoice->period_end)->id)->toBe($invoice->id);
    $this->travelTo(Carbon::parse('2026-12-01'));
    $next = $service->generate($this->tenant, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')->endOfDay());
    expect((float) $next->subtotal)->toBe(300.0);
});

it('bills purchased editor seats and highest monthly access with marginal discounts', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', ['hr'], 'GHS', 3, false, now());
    foreach (range(1, 101) as $id) {
        TenantBillingMonthlyUser::create(['tenant_id' => $this->tenant->id, 'user_id' => $id, 'month' => now()->startOfMonth(), 'level' => 'operational']);
    }
    $this->travelTo(Carbon::parse('2026-11-01'));
    $invoice = app(InvoicingService::class)->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect((float) $invoice->subtotal)->toBe(3847.5);
});

it('uses the historical agreement when generating a late invoice after a plan change', function (): void {
    $service = app(BillingAgreementService::class);
    $old = $service->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    $service->schedule($this->tenant, 'managed_business', [], 'GHS', 5, false, now()->addMonth());
    $this->travelTo(Carbon::parse('2026-12-01'));
    expect($service->activeFor($this->tenant)->plan_slug)->toBe('managed_business');
    $invoice = app(InvoicingService::class)->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect((float) $invoice->subtotal)->toBe(300.0)->and($invoice->meta['billing_agreement_id'])->toBe($old->id);
});

it('rejects retroactive mid-month duplicate and currency-changing agreements', function (string $date, string $currency): void {
    $this->travelTo(Carbon::parse('2026-10-20'));
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], $currency, 2, false, Carbon::parse($date));
})->with([['2026-10-01', 'GHS'], ['2026-11-15', 'GHS'], ['2026-11-01', 'USD']])->throws(ValidationException::class);

it('rejects mutation of an existing agreement and keeps future agreements inactive', function (): void {
    $agreement = app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now()->addMonth());
    expect(app(BillingAgreementService::class)->activeFor($this->tenant))->toBeNull();
    $agreement->update(['editor_seats' => 99]);
})->throws(DomainException::class);

it('rejects invoices for unfinished or partial billing periods', function (): void {
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now());
    app(InvoicingService::class)->generate($this->tenant, now()->startOfMonth(), now()->endOfMonth());
})->throws(DomainException::class);

it('does not install any hybrid agreements through ordinary package seeding', function (): void {
    $before = TenantBillingAgreement::count();
    $this->seed(Database\Seeders\PackageSeeder::class);
    expect(TenantBillingAgreement::count())->toBe($before);
});

it('preserves legacy usage rates and markup on a late pre-adoption invoice', function (): void {
    $package = Package::factory()->create(['price_ghs' => 100, 'markup_percentage' => 10]);
    $this->tenant->update(['package_id' => $package->id, 'markup_percentage' => 0]);
    App\Models\UsagePrice::create(['target_type' => Package::class, 'target_id' => $package->id, 'metric' => UsageMetric::STORAGE_BYTES, 'currency' => 'GHS', 'unit_price' => 10, 'unit_quantity' => 1]);
    UsageRollup::create(['tenant_id' => $this->tenant->id, 'period' => 'day', 'period_start' => now(), 'metric' => UsageMetric::STORAGE_BYTES, 'dimensions' => [], 'value' => 2]);
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, now()->addMonth()->startOfMonth());
    $this->travelTo(Carbon::parse('2026-12-01'));
    $invoice = app(InvoicingService::class)->generate($this->tenant, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay());
    expect((float) $invoice->subtotal)->toBe(122.0);
});

it('rejects adoption until recurring provider billing is terminated and aligned', function (string $status, ?string $endsAt, ?string $periodEnd): void {
    App\Models\Subscription::create(['tenant_id' => $this->tenant->id, 'name' => 'legacy', 'provider_id' => 'sub_example', 'provider_status' => $status, 'provider_plan' => 'legacy', 'ends_at' => $endsAt, 'current_period_end' => $periodEnd]);
    app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, Carbon::parse('2026-11-01'));
})->with([
    ['active', null, '2026-10-31'],
    ['past_due', null, null],
    ['canceled', '2026-12-01', '2026-10-31'],
    ['canceled', '2026-10-31', '2026-12-01'],
])->throws(ValidationException::class);

it('permits a reconciled terminated provider subscription without restarting it', function (): void {
    $subscription = App\Models\Subscription::create(['tenant_id' => $this->tenant->id, 'name' => 'legacy', 'provider_id' => 'sub_cancelled', 'provider_status' => 'canceled', 'provider_plan' => 'legacy', 'current_period_end' => '2026-10-31']);
    $agreement = app(BillingAgreementService::class)->schedule($this->tenant, 'essentials', [], 'GHS', 2, false, Carbon::parse('2026-11-01'));
    expect($agreement->exists)->toBeTrue()->and($subscription->fresh()->provider_status)->toBe('canceled');
});
