<?php

use App\Enum\UsageMetric;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\UsagePrice;
use App\Models\UsageRollup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('accrued metered charges are calculated from usage rollups', function () {
    $package = Package::factory()->create(['price' => 50.00]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);

    // Setup pricing: $0.05 per request, $0.01 per MB storage
    UsagePrice::create([
        'target_type' => Package::class,
        'target_id' => $package->id,
        'metric' => UsageMetric::REQUEST_COUNT,
        'unit_price' => 0.05,
    ]);

    UsagePrice::create([
        'target_type' => Package::class,
        'target_id' => $package->id,
        'metric' => UsageMetric::STORAGE_BYTES,
        'unit_price' => 0.01,
        'unit_quantity' => 1024 * 1024, // per MB
    ]);

    // Record usage rollups for current month
    $start = now()->startOfMonth();

    UsageRollup::create([
        'tenant_id' => $tenant->id,
        'metric' => UsageMetric::REQUEST_COUNT,
        'period' => 'day',
        'period_start' => $start->copy()->addDay(),
        'value' => 200,
        'dimensions_hash' => 'test',
    ]);

    UsageRollup::create([
        'tenant_id' => $tenant->id,
        'metric' => UsageMetric::STORAGE_BYTES,
        'period' => 'day',
        'period_start' => $start->copy()->addDays(2),
        'value' => 5 * 1024 * 1024, // 5 MB
        'dimensions_hash' => 'test',
    ]);

    // Call the private method via reflection
    $controller = new \App\Http\Controllers\Billing\BillingController();
    $method = new ReflectionMethod($controller, 'calculateAccruedMetered');

    $total = $method->invoke($controller, $tenant);

    // 200 requests * $0.05 = $10.00
    // 5 MB * $0.01/MB = $0.05
    expect($total)->toBeGreaterThan(0.0);
    expect(round($total, 2))->toEqual(10.05);
});

test('pricing service calculates cost correctly per tenant', function () {
    $package = Package::factory()->create(['price' => 50.00]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);

    UsagePrice::create([
        'target_type' => Package::class,
        'target_id' => $package->id,
        'metric' => UsageMetric::REQUEST_COUNT,
        'unit_price' => 0.10,
    ]);

    $pricingService = new \App\Services\Tenancy\PricingService();

    // 500 requests at $0.10 each = $50.00
    $cost = $pricingService->calculateCost($tenant, UsageMetric::REQUEST_COUNT, 500);
    expect(round($cost, 2))->toEqual(50.0);

    // No pricing for storage => $0
    $cost = $pricingService->calculateCost($tenant, UsageMetric::STORAGE_BYTES, 1000000);
    expect($cost)->toEqual(0.0);
});
