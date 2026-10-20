<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ModuleConflictException;
use App\Exceptions\ModuleDependencyException;
use App\Exceptions\ModuleEntitlementException;
use App\Exceptions\ModuleNotFoundException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Services\Tenancy\TenantDatabaseManager;
use App\Services\Tenancy\TenantMigrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

final class ModuleManager
{
    /**
     * @var Collection<string, array<string, mixed>>
     */
    private Collection $modules;

    private string $modulesPath;

    private bool $discovered = false;

    public function __construct()
    {
        $this->modulesPath = app_path('Modules');
        $this->modules = collect();
    }

    /**
     * Discover all available modules from the Modules directory.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function discoverModules(): Collection
    {
        if ($this->discovered) {
            return $this->modules;
        }

        if (! File::isDirectory($this->modulesPath)) {
            $this->discovered = true;

            return $this->modules;
        }

        $directories = File::directories($this->modulesPath);

        foreach ($directories as $dir) {
            $moduleName = basename($dir);
            $manifestPath = "{$dir}/Config/module.php";

            if (File::exists($manifestPath)) {
                $manifest = require $manifestPath;
                $this->modules->put($manifest['slug'], array_merge($manifest, [
                    'path' => $dir,
                    'directory' => $moduleName,
                ]));
            }
        }

        $this->discovered = true;

        return $this->modules;
    }

    /**
     * Get all discovered modules.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function all(): Collection
    {
        return $this->discoverModules();
    }

    /**
     * Find a module by its slug.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return $this->discoverModules()->get($slug);
    }

    /**
     * Check if a module exists.
     */
    public function exists(string $slug): bool
    {
        return $this->discoverModules()->has($slug);
    }

    /**
     * Check if a module is enabled for a tenant.
     */
    public function isEnabledForTenant(string $slug, Tenant $tenant): bool
    {
        // Core module is always enabled
        if ($slug === 'core') {
            return true;
        }

        $enabled = Cache::remember(
            "tenant.{$tenant->id}.module.{$slug}",
            now()->addMinutes(10),
            fn () => TenantModule::where('tenant_id', $tenant->id)
                ->where('module_slug', $slug)
                ->where('is_enabled', true)
                ->exists()
        );

        $module = $this->find($slug);
        if (! $enabled || $module === null) {
            return false;
        }

        try {
            $this->checkEntitlements($module, $tenant);
        } catch (ModuleEntitlementException) {
            return false;
        }

        return true;
    }

    public function isWebsiteExtensionForTenant(string $slug, Tenant $tenant): bool
    {
        $module = $this->find($slug);
        $class = $module['provider'] ?? null;
        if (! is_string($class) || ! is_subclass_of($class, \App\Modules\CmsCore\Providers\CmsSiteServiceProvider::class)) {
            return false;
        }
        $provider = app()->getProvider($class) ?? new $class(app());

        return $provider instanceof \App\Modules\CmsCore\Providers\CmsSiteServiceProvider
            && $provider->getModuleSlug() === $slug && $provider->getTenantSlug() === $tenant->slug;
    }

    /**
     * Enable a module for a tenant.
     *
     * @throws ModuleNotFoundException
     * @throws ModuleDependencyException
     * @throws ModuleConflictException
     */
    public function enableForTenant(string $slug, Tenant $tenant): TenantModule
    {
        $module = $this->assertCanEnable($slug, $tenant);

        // Enable the module
        $tenantModule = TenantModule::updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_slug' => $slug],
            [
                'is_enabled' => true,
                'enabled_at' => now(),
                'disabled_at' => null,
                'version' => $module['version'] ?? '1.0.0',
            ]
        );

        // Clear cache
        $this->clearCache($slug, $tenant);

        // Sync module features to tenant
        $this->syncModuleFeatures($slug, $tenant);
        $this->syncModulePermissions($module);
        $this->grantModulePermissionsToTenantAdmins($module, $tenant);

        return $tenantModule;
    }

    /**
     * Enable a module for a tenant after making sure its tables exist in that tenant's
     * database (the tenant's own database, or the shared one). If the migrations fail the
     * module is not enabled.
     *
     * Use this from anywhere a person or command enables a module (web, CLI).
     *
     * @throws ModuleNotFoundException
     * @throws RuntimeException When the module's migrations fail.
     */
    public function enableForTenantWithMigrations(string $slug, Tenant $tenant): TenantModule
    {
        $module = $this->assertCanEnable($slug, $tenant);

        $this->migrateForTenant($module, $tenant);

        return $this->enableForTenant($slug, $tenant);
    }

    /**
     * Run a module's migrations against the database the tenant uses.
     *
     * @param  array<string, mixed>  $module
     *
     * @throws RuntimeException
     */
    public function migrateForTenant(array $module, Tenant $tenant): void
    {
        $migrationPath = $module['path'].'/Database/Migrations';

        if (! is_dir($migrationPath)) {
            return;
        }

        $dbManager = app(TenantDatabaseManager::class);

        if ($tenant->requiresDedicatedDb()) {
            $dbManager->configure($tenant);
        } else {
            $dbManager->configureShared();
        }

        $relativePath = str_replace(base_path().'/', '', $migrationPath);
        $result = app(TenantMigrator::class)->migrate('tenant', $relativePath, true);

        if ($result['exitCode'] !== 0) {
            throw new RuntimeException("Module [{$module['slug']}] migration failed with exit code {$result['exitCode']}.");
        }
    }

    /**
     * Disable a module for a tenant.
     *
     * @throws ModuleNotFoundException
     * @throws ModuleDependencyException
     */
    public function disableForTenant(string $slug, Tenant $tenant): void
    {
        $module = $this->find($slug);

        if (! $module) {
            throw new ModuleNotFoundException("Module '{$slug}' not found");
        }

        // Core module cannot be disabled
        if ($slug === 'core') {
            throw new ModuleDependencyException("Module 'core' cannot be disabled");
        }

        // Check if other enabled modules depend on this one
        $this->checkDependents($slug, $tenant);

        // Disable the module
        TenantModule::where('tenant_id', $tenant->id)
            ->where('module_slug', $slug)
            ->update([
                'is_enabled' => false,
                'disabled_at' => now(),
            ]);

        // Clear cache
        $this->clearCache($slug, $tenant);

        // Disable module features for tenant
        $this->disableModuleFeatures($slug, $tenant);
    }

    /**
     * Get all enabled modules for a tenant.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function getEnabledForTenant(Tenant $tenant): Collection
    {
        return Cache::remember(
            "tenant.{$tenant->id}.enabled_modules",
            now()->addMinutes(10),
            function () use ($tenant) {
                $enabledSlugs = TenantModule::where('tenant_id', $tenant->id)
                    ->where('is_enabled', true)
                    ->pluck('module_slug');

                // Always include core module
                if (! $enabledSlugs->contains('core')) {
                    $enabledSlugs->push('core');
                }

                return $this->discoverModules()->filter(
                    fn ($module) => $enabledSlugs->contains($module['slug'])
                );
            }
        )->filter(fn (array $module): bool => $this->isEnabledForTenant($module['slug'], $tenant));
    }

    /**
     * Get modules by pricing tier.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function getByTier(string $tier): Collection
    {
        return $this->discoverModules()->filter(
            fn ($module) => ($module['tier'] ?? 'free') === $tier
        );
    }

    /**
     * Sync module features to the tenant's feature table.
     */
    public function syncModuleFeatures(string $slug, Tenant $tenant): void
    {
        $module = $this->find($slug);

        if (! $module || empty($module['features'])) {
            return;
        }

        foreach ($module['features'] as $featureKey => $featureConfig) {
            $tenant->features()->updateOrCreate(
                ['feature_key' => $featureKey],
                [
                    'enabled' => true,
                    'meta' => [
                        'type' => $featureConfig['type'] ?? 'boolean',
                        'value' => $featureConfig['default'] ?? null,
                        'source' => 'module',
                        'module_slug' => $slug,
                    ],
                ]
            );

            Cache::forget("tenant_{$tenant->id}_feature_{$featureKey}");
        }
    }

    /**
     * Disable module features for a tenant.
     */
    public function disableModuleFeatures(string $slug, Tenant $tenant): void
    {
        $module = $this->find($slug);

        if (! $module || empty($module['features'])) {
            return;
        }

        foreach (array_keys($module['features']) as $featureKey) {
            $tenant->features()
                ->where('feature_key', $featureKey)
                ->whereJsonContains('meta->module_slug', $slug)
                ->update(['enabled' => false]);

            Cache::forget("tenant_{$tenant->id}_feature_{$featureKey}");
        }
    }

    /**
     * Get the modules path.
     */
    public function getModulesPath(): string
    {
        return $this->modulesPath;
    }

    /**
     * Ensure a module can be enabled for a tenant and return its manifest.
     *
     * @return array<string, mixed>
     *
     * @throws ModuleNotFoundException
     * @throws ModuleDependencyException
     * @throws ModuleConflictException
     */
    public function assertCanEnable(string $slug, Tenant $tenant): array
    {
        $module = $this->find($slug);

        if (! $module) {
            throw new ModuleNotFoundException("Module '{$slug}' not found");
        }

        $this->checkDependencies($module, $tenant);
        $this->checkConflicts($module, $tenant);
        $this->checkEntitlements($module, $tenant);

        return $module;
    }

    /**
     * Inspect a module against the tenant's current entitlements without
     * throwing — useful for catalog UIs that need to render lock states.
     *
     * @param  array<string, mixed>|string  $moduleOrSlug
     */
    public function canEnableForTenant(array|string $moduleOrSlug, Tenant $tenant): bool
    {
        $module = is_string($moduleOrSlug) ? $this->find($moduleOrSlug) : $moduleOrSlug;

        if (! $module) {
            return false;
        }

        try {
            $this->checkDependencies($module, $tenant);
            $this->checkConflicts($module, $tenant);
            $this->checkEntitlements($module, $tenant);
        } catch (ModuleDependencyException|ModuleConflictException|ModuleEntitlementException) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function missingFeaturesForTenant(string $slug, Tenant $tenant): array
    {
        $module = $this->find($slug);

        if (! $module) {
            return [];
        }

        return array_values(array_filter(
            $this->requiredFeaturesForTenant($module, $tenant),
            fn (string $feature): bool => ! $this->tenantHasFeature($tenant, $feature),
        ));
    }

    /**
     * Legacy packages retain their policy until explicitly given module grants.
     * Bespoke modules continue to use their own required_features.
     *
     * @param  array<string, mixed>  $module
     * @return array<int, string>
     */
    private function requiredFeaturesForTenant(array $module, Tenant $tenant): array
    {
        $agreements = app(Tenancy\BillingAgreementService::class);
        if ($agreements->activeFor($tenant) !== null) {
            if ($this->isWebsiteExtensionForTenant($module['slug'], $tenant)) {
                return array_values(array_unique(array_merge($module['required_features'] ?? [], ['custom-domains', 'module-cms-core'])));
            }

            return $module['slug'] === 'core' ? [] : array_values(array_unique(array_merge(
                $module['required_features'] ?? [], ['module-'.$module['slug']],
            )));
        }

        $required = $module['required_features'] ?? [];
        $plans = config('packages.plans', []);
        $managedModules = array_unique(array_merge(...array_column($plans, 'modules')));

        if ($module['slug'] === 'core' || ! in_array($module['slug'], $managedModules, true)) {
            return $required;
        }

        $package = $tenant->package_id !== null
            ? \App\Models\Package::query()->find($tenant->package_id)
            : null;
        $explicitPackage = $package !== null && $package->features()->where('features.slug', 'like', 'module-%')->exists();
        $explicitTenant = TenantFeature::query()->where('tenant_id', $tenant->id)
            ->where('feature_key', 'like', 'module-%')
            ->where(fn ($query) => $query->whereNull('meta->source')->orWhere('meta->source', '!=', 'package'))
            ->exists();

        if (isset($plans[$package?->slug]) || $explicitPackage || $explicitTenant) {
            $required[] = 'module-'.$module['slug'];
        }

        return array_values(array_unique($required));
    }

    /**
     * Resolve "does the tenant effectively have this feature?" by checking
     * the granular tenant_features table first (per-tenant overrides,
     * module-added flags), then falling back to the tenant's package
     * entitlements via the package_features pivot.
     *
     * Package-sourced copies in tenant_features are ignored so stale copies
     * cannot preserve access after a downgrade. The current package is authoritative.
     */
    private function tenantHasFeature(Tenant $tenant, string $featureSlug): bool
    {
        $agreements = app(Tenancy\BillingAgreementService::class);
        if (($agreement = $agreements->activeFor($tenant)) !== null) {
            if (str_starts_with($featureSlug, 'module-')) {
                return in_array(mb_substr($featureSlug, 7), $agreements->grantedModules($agreement), true);
            }

            return in_array($featureSlug, $agreements->grantedFeatures($agreement), true);
        }

        $hasDirect = TenantFeature::query()
            ->where('tenant_id', $tenant->id)
            ->where('feature_key', $featureSlug)
            ->where(fn ($query) => $query->whereNull('meta->source')->orWhere('meta->source', '!=', 'package'))
            ->where('enabled', true)
            ->exists();

        if ($hasDirect) {
            return true;
        }

        if ($tenant->package_id === null) {
            return false;
        }

        return DB::table('package_features')
            ->join('features', 'features.id', '=', 'package_features.feature_id')
            ->where('package_features.package_id', $tenant->package_id)
            ->where('features.slug', $featureSlug)
            ->when(str_starts_with($featureSlug, 'module-'), fn ($query) => $query->whereIn('package_features.value', ['1', 'true']))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $module
     *
     * @throws ModuleEntitlementException
     */
    private function checkEntitlements(array $module, Tenant $tenant): void
    {
        if (app(Tenancy\BillingAgreementService::class)->activeFor($tenant) !== null
            && $this->isWebsiteExtensionForTenant($module['slug'], $tenant)
            && ! $this->isEnabledForTenant('cms-core', $tenant)) {
            throw new ModuleEntitlementException('A tenant website extension requires the CMS module to be enabled.');
        }
        $required = $this->requiredFeaturesForTenant($module, $tenant);

        if ($required === []) {
            return;
        }

        $missing = [];

        foreach ($required as $feature) {
            if (! $this->tenantHasFeature($tenant, $feature)) {
                $missing[] = $feature;
            }
        }

        if ($missing !== []) {
            throw new ModuleEntitlementException(
                "Module '{$module['slug']}' requires features not on tenant's package: ".implode(', ', $missing)
            );
        }
    }

    /**
     * Check if all dependencies are enabled.
     *
     * @param  array<string, mixed>  $module
     *
     * @throws ModuleDependencyException
     */
    private function checkDependencies(array $module, Tenant $tenant): void
    {
        $dependencies = $module['depends_on'] ?? [];

        foreach ($dependencies as $dependency) {
            if (! $this->isEnabledForTenant($dependency, $tenant)) {
                throw new ModuleDependencyException(
                    "Module '{$module['slug']}' requires '{$dependency}' to be enabled first"
                );
            }
        }
    }

    /**
     * Check for conflicts with enabled modules.
     *
     * @param  array<string, mixed>  $module
     *
     * @throws ModuleConflictException
     */
    private function checkConflicts(array $module, Tenant $tenant): void
    {
        $conflicts = $module['conflicts_with'] ?? [];

        foreach ($conflicts as $conflict) {
            if ($this->isEnabledForTenant($conflict, $tenant)) {
                throw new ModuleConflictException(
                    "Module '{$module['slug']}' conflicts with '{$conflict}'"
                );
            }
        }
    }

    /**
     * Check if other modules depend on this one before disabling.
     *
     * @throws ModuleDependencyException
     */
    private function checkDependents(string $slug, Tenant $tenant): void
    {
        $dependentModules = $this->discoverModules()->filter(function ($module) use ($slug) {
            $dependencies = $module['depends_on'] ?? [];

            return in_array($slug, $dependencies);
        });

        foreach ($dependentModules as $dependent) {
            if ($this->isEnabledForTenant($dependent['slug'], $tenant)) {
                throw new ModuleDependencyException(
                    "Cannot disable '{$slug}' because '{$dependent['slug']}' depends on it"
                );
            }
        }
    }

    /**
     * Clear module-related caches for a tenant.
     */
    private function clearCache(string $slug, Tenant $tenant): void
    {
        Cache::forget("tenant.{$tenant->id}.module.{$slug}");
        Cache::forget("tenant.{$tenant->id}.enabled_modules");
        Tenancy\EntitlementService::forgetAllowedPermissions($tenant->id);
    }

    /**
     * Sync module permissions to the global permissions table.
     *
     * @param  array<string, mixed>  $module
     */
    private function syncModulePermissions(array $module): void
    {
        $permissions = $module['permissions'] ?? [];

        if (empty($permissions)) {
            return;
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $category = $module['permission_category'] ?? $module['slug'] ?? 'module';

        // firstOrCreate (not updateOrCreate) so we never clobber a category set
        // by an earlier seeder or module — the role-grouping UI relies on stable
        // categories (e.g. existing "user" / "role" groups must not collapse into
        // a single "core" group just because Core now declares those permissions).
        $permissionNames = [];
        foreach ($permissions as $key => $value) {
            $permissionNames[] = is_string($key) ? $key : (string) $value;
        }

        foreach ($permissionNames as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
            ], [
                'uuid' => (string) Str::uuid(),
                'category' => $category,
            ]);
        }
    }

    /**
     * Grant the module's permissions to the tenant's Org Superadmin / Org Admin
     * roles so future administrators inherit access, and preserve direct user grants.
     *
     * Role permission pivots are not team-scoped: only tenant-owned roles may
     * receive these grants. Tenant copies inherit global baseline permissions
     * additively; global roles remain unchanged. Direct user grants
     * are scoped to the active tenant by Spatie teams.
     *
     * @param  array<string, mixed>  $module
     */
    private function grantModulePermissionsToTenantAdmins(array $module, Tenant $tenant): void
    {
        $permissions = $module['permissions'] ?? [];

        if (empty($permissions)) {
            return;
        }

        $permissionNames = [];
        foreach ($permissions as $key => $value) {
            $permissionNames[] = is_string($key) ? $key : (string) $value;
        }

        $previousTeamId = app(PermissionRegistrar::class)->getPermissionsTeamId();
        setPermissionsTeamId($tenant->id);

        try {
            $globalRoles = Role::query()
                ->whereNull('tenant_id')
                ->where('guard_name', 'web')
                ->whereIn('name', ['Org Superadmin', 'Org Admin'])
                ->with('permissions')
                ->get()
                ->keyBy('name');

            foreach (['Org Superadmin', 'Org Admin'] as $roleName) {
                $globalRole = $globalRoles->get($roleName);
                $role = Role::query()->firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'guard_name' => 'web',
                    'name' => $roleName,
                ], [
                    'slug' => $globalRole?->getAttribute('slug') ?? Str::slug($roleName, '_'),
                ]);

                if ($globalRole !== null) {
                    $role->givePermissionTo($globalRole->permissions);
                }

                $role->givePermissionTo($permissionNames);
            }

            $tenant->users()
                ->get()
                ->each(function ($user) use ($permissionNames): void {
                    if ($user->hasRole(['Org Superadmin', 'Org Admin'])) {
                        $user->givePermissionTo($permissionNames);
                    }
                });
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
