<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Cache;

final class EntitlementService
{
    /** Cache TTL for the per-tenant allowed-permissions list. */
    public const ALLOWED_PERMISSIONS_TTL_MINUTES = 10;

    public function __construct(private readonly TenantContext $context) {}

    /** Cache key for the tenant's allowed-permission set. */
    public static function allowedPermissionsCacheKey(string $tenantId): string
    {
        return "tenant_{$tenantId}_allowed_permissions";
    }

    /** Invalidate the cached permission set for a tenant (e.g. after a module enable/disable). */
    public static function forgetAllowedPermissions(string $tenantId): void
    {
        Cache::forget(self::allowedPermissionsCacheKey($tenantId));
    }

    /**
     * Check if the current tenant is entitled to a specific permission.
     * This checks if the permission is tied to any features, and if so,
     * whether at least one of those features is enabled for the tenant.
     */
    public function isEntitled(string $permissionName): bool
    {
        $tenantId = $this->context->activeTenantId();

        if (! $tenantId) {
            return true;
        }

        $featureSlugs = $this->getFeaturesForPermission($permissionName);

        if (empty($featureSlugs)) {
            return true;
        }

        foreach ($featureSlugs as $slug) {
            if ($this->isFeatureEnabledForTenant($tenantId, $slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permission names this tenant can assign to custom roles.
     *
     * Result is the union of:
     *   - The baseline tenant permissions (Permission::BASELINE_TENANT_PERMISSIONS)
     *   - Permissions declared by every module currently enabled for the tenant
     *
     * Feature-flagged permissions are filtered down to those whose linked
     * feature is enabled on the tenant — so plan-gated features (e.g.
     * custom domains) only appear once the tenant has been entitled.
     *
     * @return array<int, string>
     */
    public function getAllowedPermissionsForTenant(string $tenantId): array
    {
        return Cache::remember(
            self::allowedPermissionsCacheKey($tenantId),
            now()->addMinutes(self::ALLOWED_PERMISSIONS_TTL_MINUTES),
            function () use ($tenantId): array {
                $candidateNames = $this->candidatePermissionNames($tenantId);

                if ($candidateNames === []) {
                    return [];
                }

                $permissions = Permission::query()
                    ->whereIn('name', $candidateNames)
                    ->with('features:id,slug')
                    ->get();

                return $permissions->filter(function (Permission $permission) use ($tenantId): bool {
                    if ($permission->features->isEmpty()) {
                        return true;
                    }

                    foreach ($permission->features as $feature) {
                        if ($this->isFeatureEnabledForTenant($tenantId, $feature->slug)) {
                            return true;
                        }
                    }

                    return false;
                })->pluck('name')->values()->all();
            }
        );
    }

    /**
     * Compose the candidate permission name pool for a tenant: baseline +
     * everything declared by every enabled module.
     *
     * @return array<int, string>
     */
    private function candidatePermissionNames(string $tenantId): array
    {
        $tenant = Tenant::query()->find($tenantId);

        $moduleNames = $tenant
            ? app(ModuleManager::class)
                ->getEnabledForTenant($tenant)
                ->flatMap(fn (array $module): array => $module['permissions'] ?? [])
                ->all()
            : [];

        return array_values(array_unique(array_merge(
            Permission::BASELINE_TENANT_PERMISSIONS,
            $moduleNames
        )));
    }

    /**
     * Get all feature slugs that "unlock" a specific permission.
     */
    private function getFeaturesForPermission(string $permissionName): array
    {
        return Feature::whereHas('permissions', function ($query) use ($permissionName) {
            $query->where('name', $permissionName);
        })->pluck('slug')->toArray();
    }

    private function isFeatureEnabledForTenant(string $tenantId, string $featureSlug): bool
    {
        return TenantFeature::where('tenant_id', $tenantId)
            ->where('feature_key', $featureSlug)
            ->where('enabled', true)
            ->exists();
    }
}
