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

    App\Models\TenantModule::query()->create(['tenant_id' => $tenant->id, 'module_slug' => 'cms-core', 'is_enabled' => true, 'version' => '1.0.0']);

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

    App\Models\TenantModule::query()->create(['tenant_id' => $tenant->id, 'module_slug' => 'cms-core', 'is_enabled' => true, 'version' => '1.0.0']);

    expect($this->mm->canEnableForTenant('site-thyroid-ghana-foundation', $tenant))->toBeTrue();
});

test('canEnableForTenant returns false for unknown module slug', function (): void {
    $tenant = Tenant::factory()->create();

    expect($this->mm->canEnableForTenant('nonexistent-module', $tenant))->toBeFalse();
});

test('community cannot enable CRM while Team can', function (): void {
    $community = Package::factory()->create(['slug' => 'community']);
    $team = Package::factory()->create(['slug' => 'team']);
    $feature = Feature::factory()->create(['slug' => 'module-crm']);
    attachFeatureToPackage($team, $feature);
    $tenant = Tenant::factory()->create(['package_id' => $community->id]);

    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeFalse();
    expect(fn () => $this->mm->assertCanEnable('crm', $tenant))
        ->toThrow(App\Exceptions\ModuleEntitlementException::class);

    $tenant->update(['package_id' => $team->id]);
    $tenant->unsetRelation('package');
    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeTrue();
    expect($this->mm->assertCanEnable('crm', $tenant)['slug'])->toBe('crm');
});

test('explicit module grants opt a legacy package into module restrictions', function (): void {
    $package = Package::factory()->create(['slug' => 'legacy-custom']);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeTrue();

    $feature = Feature::factory()->create(['slug' => 'module-cms-core']);
    attachFeatureToPackage($package, $feature);
    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeFalse();

    TenantFeature::create(['tenant_id' => $tenant->id, 'feature_key' => 'module-crm', 'enabled' => true]);
    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeTrue();
});

test('module plan grant does not bypass dependency or bespoke site requirements', function (): void {
    $package = Package::factory()->create(['slug' => 'team']);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $module = $this->mm->find('crm');
    $module['depends_on'] = ['cms-core'];
    $feature = Feature::factory()->create(['slug' => 'module-crm']);
    attachFeatureToPackage($package, $feature);
    expect($this->mm->canEnableForTenant($module, $tenant))->toBeFalse();
    expect($this->mm->canEnableForTenant('site-thyroid-ghana-foundation', $tenant))->toBeFalse();
    expect($this->mm->missingFeaturesForTenant('site-thyroid-ghana-foundation', $tenant))->toBe(['custom-domains']);
});

test('false module package feature is not a grant', function (): void {
    $package = Package::factory()->create(['slug' => 'community']);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $feature = Feature::factory()->create(['slug' => 'module-crm']);
    attachFeatureToPackage($package, $feature, '0');
    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeFalse();
});

test('package seeder synchronizes bundles without changing legacy prices or bespoke grants', function (): void {
    $legacy = Package::factory()->create(['slug' => 'enterprise', 'price' => 147]);
    $bespoke = Feature::factory()->create(['slug' => 'bespoke-client-feature']);
    attachFeatureToPackage($legacy, $bespoke);
    $this->seed(Database\Seeders\PackageSeeder::class);
    $this->seed(Database\Seeders\PackageSeeder::class);

    foreach (config('packages.plans') as $slug => $plan) {
        $package = Package::query()->where('slug', $slug)->firstOrFail();
        expect((float) $package->price)->toBe(0.0);
        expect((float) $package->getAttribute('price_ghs'))->toBe(0.0);
        expect($package->billing_model)->toBe(Package::BILLING_MODEL_FLAT_RATE);
        foreach ($plan['modules'] as $module) {
            expect($package->features()->where('slug', 'module-'.$module)->exists())->toBeTrue();
        }
        expect($package->features()->whereIn('slug', ['sso', 'file-retention', 'custom-alerts'])->exists())->toBeFalse();
    }
    expect($legacy->fresh()->features()->where('slug', 'custom-domains')->exists())->toBeTrue();
    expect($legacy->fresh()->features()->where('slug', 'bespoke-client-feature')->exists())->toBeTrue();
    expect((float) $legacy->fresh()->price)->toBe(147.0);
});

test('catalog locks CRM for Community and offers it to Team', function (): void {
    $this->seed(Database\Seeders\PackageSeeder::class);
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user, [
        'package_id' => Package::query()->where('slug', 'community')->firstOrFail()->id,
    ]);
    grantOrganisationAdminAccess($user);
    $request = Illuminate\Http\Request::create('/modules');
    $request->setUserResolver(fn () => $user);
    $controller = app(App\Http\Controllers\Tenant\ModuleCatalogController::class);
    $modules = $controller->index($request)->getData()['modules'];
    expect($modules->firstWhere('slug', 'crm')['status'])->toBe('locked');

    $tenant->update(['package_id' => Package::query()->where('slug', 'team')->firstOrFail()->id]);
    $modules = $controller->index($request)->getData()['modules'];
    expect($modules->firstWhere('slug', 'crm')['status'])->toBe('available');
});

test('legacy packages and tenants without packages keep their module policy', function (?string $slug): void {
    $package = $slug !== null ? Package::factory()->create(['slug' => $slug]) : null;
    $tenant = Tenant::factory()->create(['package_id' => $package?->id]);
    expect($this->mm->canEnableForTenant('crm', $tenant))->toBeTrue();
})->with([null, 'starter', 'pro', 'enterprise']);

test('downgrade revokes cached module access without deleting its enabled row', function (): void {
    $this->seed(Database\Seeders\PackageSeeder::class);
    $team = Package::query()->where('slug', 'team')->firstOrFail();
    $community = Package::query()->where('slug', 'community')->firstOrFail();
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['package_id' => $team->id]);
    grantOrganisationAdminAccess($user);
    $row = $this->mm->enableForTenant('crm', $tenant);
    $tenant->syncFeaturesFromPackage();
    expect($this->mm->isEnabledForTenant('crm', $tenant))->toBeTrue();
    expect($this->mm->getEnabledForTenant($tenant)->has('crm'))->toBeTrue();

    $tenant->update(['package_id' => $community->id]);
    expect($this->mm->isEnabledForTenant('crm', $tenant))->toBeFalse();
    expect($this->mm->getEnabledForTenant($tenant)->has('crm'))->toBeFalse();
    expect((bool) $row->fresh()->is_enabled)->toBeTrue();

    $request = Illuminate\Http\Request::create('/modules');
    $request->setUserResolver(fn () => $user);
    $modules = app(App\Http\Controllers\Tenant\ModuleCatalogController::class)->index($request)->getData()['modules'];
    expect($modules->firstWhere('slug', 'crm')['status'])->toBe('locked');

    $middleware = app(App\Http\Middleware\EnsureModuleEnabled::class);
    expect(fn () => $middleware->handle($request, fn () => response('allowed'), 'crm'))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
