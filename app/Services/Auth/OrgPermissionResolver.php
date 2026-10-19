<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Directorate;
use App\Modules\HrmsCore\Models\Organization\OrgPermissionAssignment;
use App\Modules\HrmsCore\Models\Organization\OrgRoleAssignment;
use App\Modules\HrmsCore\Models\Organization\Unit;
use App\Modules\HrmsCore\Services\OrgHierarchyResolver;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OrgPermissionResolver
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OrgHierarchyResolver $hierarchyResolver,
        private readonly ModuleManager $moduleManager,
    ) {}

    public static function userCacheKey(string $tenantId, int $userId): string
    {
        $version = (int) Cache::get(self::tenantVersionCacheKey($tenantId), 1);

        return "org_rbac_permissions:{$tenantId}:v{$version}:u{$userId}";
    }

    public static function tenantVersionCacheKey(string $tenantId): string
    {
        return "org_rbac_version:{$tenantId}";
    }

    /**
     * Bump the tenant-wide version key so all cached organizational permissions
     * for this tenant are immediately invalidated.
     */
    public static function flushForTenant(string $tenantId): void
    {
        $key = self::tenantVersionCacheKey($tenantId);
        $current = (int) Cache::get($key, 1);
        Cache::put($key, $current + 1, now()->addDays(7));
    }

    /**
     * Determine whether the given user inherits $ability from their Unit, Department, or Directorate.
     */
    public function userHasInheritedPermission(User $user, string $ability): bool
    {
        $tenant = $this->tenantContext->getTenant();
        if (! $tenant instanceof Tenant) {
            return false;
        }

        // Never grant landlord-only admin.* permissions via tenant organizational inheritance
        if (str_starts_with($ability, 'admin.')) {
            return false;
        }

        // Verify that if $ability belongs to a module, that module is enabled for the tenant
        $owningModuleSlug = $this->findOwningModuleSlug($ability);
        if ($owningModuleSlug !== null && ! $this->moduleManager->isEnabledForTenant($owningModuleSlug, $tenant)) {
            return false;
        }

        $inheritedPermissions = $this->getEffectivePermissionsForUser($user, $tenant);

        if (in_array($ability, $inheritedPermissions, true)) {
            return true;
        }

        $legacy = AbilityAliasService::toLegacy($ability);
        if ($legacy !== null && in_array($legacy, $inheritedPermissions, true)) {
            return true;
        }

        $modern = AbilityAliasService::toNew($ability);
        if ($modern !== null && in_array($modern, $inheritedPermissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve all permission names inherited by $user via their active Employee record's
     * Unit, Department, and Directorate memberships in $tenant.
     *
     * @return array<int, string>
     */
    public function getEffectivePermissionsForUser(User $user, Tenant $tenant): array
    {
        $cacheKey = self::userCacheKey((string) $tenant->id, (int) $user->id);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user, $tenant): array {
            try {
                if (
                    ! Schema::connection('tenant')->hasTable('hrms_employees')
                    || ! Schema::connection('tenant')->hasTable('hrms_org_permission_assignments')
                ) {
                    return [];
                }

                $employee = Employee::query()
                    ->where('tenant_id', (string) $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                if (! $employee instanceof Employee) {
                    return [];
                }

                $nodes = $this->hierarchyResolver->resolveOrgNodesForEmployee($employee);

                $unitIds = $nodes['units']->pluck('id')->all();
                $departmentIds = $nodes['departments']->pluck('id')->all();
                $directorateIds = $nodes['directorates']->pluck('id')->all();

                if ($unitIds === [] && $departmentIds === [] && $directorateIds === []) {
                    return [];
                }

                $directPermissions = OrgPermissionAssignment::query()
                    ->where('tenant_id', (string) $tenant->id)
                    ->where(function ($query) use ($unitIds, $departmentIds, $directorateIds): void {
                        if ($unitIds !== []) {
                            $query->orWhere(function ($q) use ($unitIds): void {
                                $q->where('assignable_type', Unit::class)->whereIn('assignable_id', $unitIds);
                            });
                        }
                        if ($departmentIds !== []) {
                            $query->orWhere(function ($q) use ($departmentIds): void {
                                $q->where('assignable_type', Department::class)->whereIn('assignable_id', $departmentIds);
                            });
                        }
                        if ($directorateIds !== []) {
                            $query->orWhere(function ($q) use ($directorateIds): void {
                                $q->where('assignable_type', Directorate::class)->whereIn('assignable_id', $directorateIds);
                            });
                        }
                    })
                    ->pluck('permission_name')
                    ->all();

                $roleAssignments = OrgRoleAssignment::query()
                    ->where('tenant_id', (string) $tenant->id)
                    ->where(function ($query) use ($unitIds, $departmentIds, $directorateIds): void {
                        if ($unitIds !== []) {
                            $query->orWhere(function ($q) use ($unitIds): void {
                                $q->where('assignable_type', Unit::class)->whereIn('assignable_id', $unitIds);
                            });
                        }
                        if ($departmentIds !== []) {
                            $query->orWhere(function ($q) use ($departmentIds): void {
                                $q->where('assignable_type', Department::class)->whereIn('assignable_id', $departmentIds);
                            });
                        }
                        if ($directorateIds !== []) {
                            $query->orWhere(function ($q) use ($directorateIds): void {
                                $q->where('assignable_type', Directorate::class)->whereIn('assignable_id', $directorateIds);
                            });
                        }
                    })
                    ->get(['role_id', 'role_name']);

                /** @var list<string> $rolePermissions */
                $rolePermissions = [];
                if ($roleAssignments->isNotEmpty()) {
                    $roleIds = $roleAssignments->pluck('role_id')->filter()->values()->all();
                    $roleNames = $roleAssignments->pluck('role_name')->filter()->values()->all();

                    /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $roles */
                    $roles = Role::query()
                        ->where('name', '!=', 'Superadmin')
                        ->where(function ($q) use ($tenant): void {
                            $q->where('tenant_id', (string) $tenant->id)
                                ->orWhereNull('tenant_id');
                        })
                        ->where(function ($q) use ($roleIds, $roleNames): void {
                            if ($roleIds !== []) {
                                $q->orWhereIn('id', $roleIds);
                            }
                            if ($roleNames !== []) {
                                $q->orWhereIn('name', $roleNames);
                            }
                        })
                        ->with('permissions:id,name')
                        ->get();

                    foreach ($roles as $role) {
                        /** @var \Illuminate\Database\Eloquent\Model $permission */
                        foreach ($role->permissions as $permission) {
                            $permName = (string) $permission->getAttribute('name');
                            if ($permName !== '') {
                                $rolePermissions[] = $permName;
                            }
                        }
                    }
                }

                /** @var list<string> $directPermStrings */
                $directPermStrings = array_values(array_filter(
                    $directPermissions,
                    fn (mixed $item): bool => is_string($item) && $item !== ''
                ));

                /** @var list<string> $allCandidatePermissions */
                $allCandidatePermissions = array_values(array_unique(array_merge($directPermStrings, $rolePermissions)));

                return array_values(array_filter(
                    $allCandidatePermissions,
                    function (string $perm) use ($tenant): bool {
                        if (str_starts_with($perm, 'admin.')) {
                            return false;
                        }
                        $moduleSlug = $this->findOwningModuleSlug($perm);
                        if ($moduleSlug !== null && ! $this->moduleManager->isEnabledForTenant($moduleSlug, $tenant)) {
                            return false;
                        }

                        return true;
                    }
                ));
            } catch (Throwable) {
                return [];
            }
        });
    }

    private function findOwningModuleSlug(string $permissionName): ?string
    {
        foreach ($this->moduleManager->all() as $slug => $module) {
            $permissions = $module['permissions'] ?? [];
            if (is_array($permissions) && in_array($permissionName, $permissions, true)) {
                return (string) $slug;
            }
        }

        return null;
    }
}
