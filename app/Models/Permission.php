<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use Spatie\Permission\Models\Permission as SpatiePermission;

final class Permission extends SpatiePermission
{
    use HasUuid;

    /**
     * Baseline permissions every tenant Org Superadmin can assign regardless of
     * which modules are enabled. Module-declared permissions are unioned on top
     * by App\Services\Tenancy\EntitlementService::getAllowedPermissionsForTenant.
     *
     * Kept as a const for cheap, deterministic access — module-aware expansion
     * happens at the service layer where the active tenant is available.
     */
    public const BASELINE_TENANT_PERMISSIONS = [
        'access dashboard',
        'read user',
        'create user',
        'update user',
        'delete user',
        'read role',
        'create role',
        'update role',
        'delete role',
        'read team',
        'create team',
        'update team',
        'delete team',
        'read communication',
        'create communication',
        'update communication',
        'delete communication',
        'manage organization settings',
    ];

    /**
     * @deprecated Use BASELINE_TENANT_PERMISSIONS plus module-declared permissions
     *             via EntitlementService::getAllowedPermissionsForTenant. Retained
     *             so callers that referenced the old constant still resolve.
     */
    public const TENANT_SAFE = self::BASELINE_TENANT_PERMISSIONS;

    protected $connection = 'landlord';

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'feature_permissions');
    }
}
