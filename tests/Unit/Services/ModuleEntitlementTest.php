<?php

declare(strict_types=1);

use App\Models\Feature;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\DB;

/**
 * canEnableForTenant resolves "does this tenant have what the module needs?"
 * by walking the tenant_features table first, then falling back to the
 * tenant's package via package_features. Tests build the entitlement
 * graph from factories so they're independent of seeded state.
 */
beforeEach(function (): void {
    $this->mm = app(ModuleManager::class);
});

function attachFeatureToPackage(Package $package, Feature $feature, string $value = '1'): void
{
    DB::table('package_features')->insertOrIgnore([
        'package_id' => $package->id,
        'feature_id' => $feature->id,
        'value' => $value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('module with no required_features can be enabled for any tenant', function (): void {
    $tenant = Tenant::factory()->create();

    expect($this->mm->canEnableForTenant('cms-core', $tenant))->toBeTrue();
    expect($this->mm->missingFeaturesForTenant('cms-core', $tenant))->toBe([]);
});

test('tenant on a package that includes the required feature can enable the module', function (): void {
    $feature = Feature::factory()->create(['slug' => 'custom-domains']);
    $package = Package::factory()->create();
    attachFeatureToPackage($package, $feature);

    $tenant = Tenant::factory()->create(['package_id' => $package->id]);

    expect($this->mm->canEnableForTenant('site-thyroid-ghana-foundation', $tenant))->toBeTrue();
});

test('tenant without the required feature in package cannot enable the module', function (): void {
    Feature::factory()->create(['slug' => 'custom-domains']);
    $package = Package::factory()->create();
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);

    expect($this->mm->canEnableForTenant('site-thyroid-ghana-foundation', $tenant))->toBeFalse();
    expect($this->mm->missingFeaturesForTenant('site-thyroid-ghana-foundation', $tenant))
        ->toContain('custom-domains');
});

test('direct tenant_features grant overrides missing package entitlement', function (): void {
    Feature::factory()->create(['slug' => 'custom-domains']);
    $tenant = Tenant::factory()->create(['package_id' => null]);

    TenantFeature::create([
        'tenant_id' => $tenant->id,
        'feature_key' => 'custom-domains',
        'enabled' => true,
    ]);

    expect($this->mm->canEnableForTenant('site-thyroid-ghana-foundation', $tenant))->toBeTrue();
});

test('canEnableForTenant returns false for unknown module slug', function (): void {
    $tenant = Tenant::factory()->create();

    expect($this->mm->canEnableForTenant('nonexistent-module', $tenant))->toBeFalse();
});
