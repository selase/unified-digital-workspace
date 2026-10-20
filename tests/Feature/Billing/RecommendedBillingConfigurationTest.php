<?php

declare(strict_types=1);

use App\Enum\UsageMetric;
use App\Models\Package;
use App\Models\UsagePrice;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RecommendedUsagePriceSeeder;

it('seeds independently priced currencies and preserves administrator rate overrides', function (): void {
    $this->seed(PackageSeeder::class);
    $this->seed(RecommendedUsagePriceSeeder::class);
    $team = Package::query()->where('slug', 'team')->firstOrFail();
    $prices = UsagePrice::query()->where('target_type', Package::class)->where('target_id', $team->id)
        ->where('metric', UsageMetric::USER_ACTIVE_MONTHLY)->get()->keyBy('currency');
    expect((float) $prices['GHS']->unit_price)->toBe(25.0)
        ->and((float) $prices['USD']->unit_price)->toBe(2.0)
        ->and((float) $team->price)->toBe(0.0)
        ->and((float) $team->price_ghs)->toBe(0.0);
    $prices['GHS']->update(['unit_price' => 18]);
    $count = UsagePrice::query()->count();
    $this->seed(RecommendedUsagePriceSeeder::class);
    expect(UsagePrice::query()->count())->toBe($count)
        ->and((float) $prices['GHS']->fresh()->unit_price)->toBe(18.0);
});

it('returns a failed command status when a tenant invoice cannot be generated', function (): void {
    $package = Package::factory()->create(['price_ghs' => null]);
    App\Models\Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id]);
    $this->artisan('billing:generate-invoices', ['--month' => '2026-09'])->assertFailed();
    expect(App\Models\Invoice::query()->count())->toBe(0);
});

it('rejects invalid billing periods without creating invoices', function (): void {
    $this->artisan('billing:generate-invoices', ['--month' => '2026-13'])->assertFailed();
    expect(App\Models\Invoice::query()->count())->toBe(0);
});
