<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidGhanaSeeder;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

if (! defined('TGF_SITE')) {
    define('TGF_SITE', 'https://thyroidghanafoundation.org');
}

function tgfSiteTenant(): Tenant
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['slug' => 'thyroid-ghana-foundation', 'custom_domain' => 'thyroidghanafoundation.org', 'custom_domain_status' => 'active']);
    app(TenantDatabaseManager::class)->configure($tenant);

    foreach (['module-cms-core', 'custom-domains'] as $feature) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => $feature, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    }
    foreach (['cms-core', 'site-thyroid-ghana-foundation'] as $module) {
        TenantModule::query()->create(['tenant_id' => $tenant->id, 'module_slug' => $module, 'is_enabled' => true, 'version' => '1.0.0']);
    }
    $modules = ['finance' => 'Finance', 'memberships' => 'Memberships', 'donations' => 'Donations', 'volunteers' => 'Volunteers'];
    foreach (array_keys($modules) as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach ($modules as $directory) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$directory}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }
    Cache::flush();
    app(ThyroidGhanaSeeder::class)->run();

    return $tenant;
}
