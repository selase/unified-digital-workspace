<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\CmsCore\Database\Seeders\CmsCoreSeeder;
use App\Modules\DocumentManagement\Database\Seeders\DocumentManagementSeeder;
use App\Modules\Forums\Database\Seeders\ForumsSeeder;
use App\Modules\HrmsCore\Database\Seeders\HrmsCoreDemoSeeder;
use App\Modules\HrmsCore\Database\Seeders\HrmsCoreSeeder;
use App\Modules\IncidentManagement\Database\Seeders\IncidentManagementSeeder;
use App\Modules\Memos\Database\Seeders\MemosSeeder;
use App\Modules\ProjectManagement\Database\Seeders\ProjectManagementSeeder;
use App\Modules\QualityMonitoring\Database\Seeders\QualityMonitoringSeeder;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class CompletedModulesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'purpledot')->first();

        if (! $tenant) {
            $this->command?->warn('CompletedModulesDemoSeeder: purpledot tenant not found, skipping demo seed.');

            return;
        }

        $this->configureTenantContext($tenant);

        $tenantUser = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'dev@wearepurpledot.com')
            ->first() ?? User::query()->where('tenant_id', $tenant->id)->first();

        if (! $tenantUser) {
            $this->command?->warn('CompletedModulesDemoSeeder: no tenant user found for purpledot, skipping demo seed.');

            return;
        }

        $tenantUser->tenants()->syncWithoutDetaching([$tenant->id]);

        $moduleSlugs = [
            'hrms-core',
            'cms-core',
            'document-management',
            'memos',
            'incident-management',
            'forums',
            'project-management',
            'quality-monitoring',
        ];

        $enabledModules = $this->enableModulesForTenant($moduleSlugs, $tenant);

        $this->grantModulePermissionsToTenantUser($tenantUser, $tenant->id, $enabledModules);

        $this->call([
            HrmsCoreSeeder::class,
            HrmsCoreDemoSeeder::class,
            CmsCoreSeeder::class,
            DocumentManagementSeeder::class,
            MemosSeeder::class,
            IncidentManagementSeeder::class,
            ForumsSeeder::class,
            ProjectManagementSeeder::class,
            QualityMonitoringSeeder::class,
        ]);

        setPermissionsTeamId(null);
    }

    private function configureTenantContext(Tenant $tenant): void
    {
        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);

        $dbManager = app(TenantDatabaseManager::class);

        if ($tenant->requiresDedicatedDb()) {
            $dbManager->configure($tenant);
        } else {
            $dbManager->configureShared();
        }
    }

    /**
     * @param  array<int, string>  $moduleSlugs
     * @return array<int, string>
     */
    private function enableModulesForTenant(array $moduleSlugs, Tenant $tenant): array
    {
        $moduleManager = app(ModuleManager::class);
        $enabledModules = [];

        foreach ($moduleSlugs as $slug) {
            if (! $moduleManager->exists($slug)) {
                continue;
            }

            try {
                $moduleManager->enableForTenant($slug, $tenant);
                $enabledModules[] = $slug;
            } catch (Throwable $throwable) {
                $this->command?->warn("CompletedModulesDemoSeeder: failed enabling module {$slug} ({$throwable->getMessage()})");
            }
        }

        return $enabledModules;
    }

    /**
     * @param  array<int, string>  $enabledModules
     */
    private function grantModulePermissionsToTenantUser(User $tenantUser, string $tenantId, array $enabledModules): void
    {
        $moduleManager = app(ModuleManager::class);

        $permissionNames = collect($enabledModules)
            ->flatMap(function (string $moduleSlug) use ($moduleManager): array {
                $manifest = $moduleManager->find($moduleSlug);

                return (array) ($manifest['permissions'] ?? []);
            })
            ->filter()
            ->unique()
            ->values();

        if ($permissionNames->isEmpty()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId($tenantId);

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames->all())
            ->get();

        if ($permissions->isEmpty()) {
            return;
        }

        $tenantUser->givePermissionTo($permissions->all());
    }
}
