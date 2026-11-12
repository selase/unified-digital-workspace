<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createMonitoringContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.results-framework.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'results', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-results-framework', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('results-framework', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/ResultsFramework/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant];
}

function enableResultMeasureModules(Tenant $tenant, string ...$modules): void
{
    foreach ($modules as $module) {
        TenantModule::query()->updateOrCreate(['tenant_id' => $tenant->id, 'module_slug' => $module], ['is_enabled' => true]);
        TenantFeature::query()->updateOrCreate(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$module], ['enabled' => true, 'meta' => ['source' => 'complimentary']]);
    }
    Cache::flush();
}
