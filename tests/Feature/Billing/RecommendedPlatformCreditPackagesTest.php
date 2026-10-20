<?php

declare(strict_types=1);

use App\Modules\Store\Models\PlatformCreditPackage;
use Database\Seeders\RecommendedPlatformCreditPackageSeeder;
use Illuminate\Support\Facades\Schema;

it('seeds only email packs at the agreed GHS and USD rates', function (): void {
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);
    $packs = PlatformCreditPackage::query()->get()->keyBy('currency');

    expect($packs)->toHaveCount(2);
    foreach (['GHS' => 1500, 'USD' => 100] as $currency => $cents) {
        expect($packs[$currency]->credit_type)->toBe('email')
            ->and($packs[$currency]->amount_credits)->toBe(1000)
            ->and($packs[$currency]->price_cents)->toBe($cents)
            ->and($packs[$currency]->is_active)->toBeTrue()
            ->and($packs[$currency]->getConnectionName())->toBe('landlord');
    }
});

it('preserves catalogue overrides and stays idempotent for both currencies', function (): void {
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);
    $packs = PlatformCreditPackage::query()->get();
    foreach ($packs as $pack) {
        $pack->update(['name' => 'Operator email bundle', 'price_cents' => 75, 'is_active' => false]);
    }
    $uuids = $packs->pluck('uuid')->sort()->values()->all();
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);

    expect(PlatformCreditPackage::query()->count())->toBe(2);
    expect(PlatformCreditPackage::query()->pluck('uuid')->sort()->values()->all())->toBe($uuids);
    foreach ($packs as $pack) {
        expect($pack->fresh()->name)->toBe('Operator email bundle')
            ->and($pack->fresh()->price_cents)->toBe(75)
            ->and($pack->fresh()->is_active)->toBeFalse();
    }
});

it('makes the landlord catalogue migration safe for an existing operator catalogue', function (): void {
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);
    $ids = PlatformCreditPackage::query()->pluck('uuid')->all();
    $migration = require database_path('migrations/landlord/2026_10_20_100000_create_platform_credit_packages_table.php');
    $migration->up();
    $migration->up();

    expect(Schema::connection('landlord')->hasTable('platform_credit_packages'))->toBeTrue();
    expect(PlatformCreditPackage::query()->pluck('uuid')->all())->toBe($ids);
});
