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
     * Uses module.action.scope names; the legacy "verb noun" twins still
     * exist in the database and resolve transparently via
     * App\Services\Auth\AbilityAliasService.
     */
    public const BASELINE_TENANT_PERMISSIONS = [
        'core.dashboard.access',
        'core.users.read',
        'core.users.create',
        'core.users.update',
        'core.users.delete',
        'core.roles.read',
        'core.roles.create',
        'core.roles.update',
        'core.roles.delete',
        'core.teams.read',
        'core.teams.create',
        'core.teams.update',
        'core.teams.delete',
        'core.communications.read',
        'core.communications.create',
        'core.communications.update',
        'core.communications.delete',
        'core.settings.manage',
        'core.api-keys.manage',
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
