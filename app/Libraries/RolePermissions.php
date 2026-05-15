<?php

declare(strict_types=1);

namespace App\Libraries;

use Spatie\Permission\Models\Role;

/**
 * Seeds the canonical role -> permission grants for the three system roles.
 *
 * Permission names use the new module.action.scope convention:
 *   core.*  = tenant baseline (every Org Superadmin / Org Admin gets these)
 *   admin.* = landlord scope (Superadmin only; cross-tenant operations)
 *
 * Legacy "verb noun" names still live in the permissions table for
 * backward compatibility and resolve via App\Services\Auth\AbilityAliasService.
 * The role -> permission mirroring migration (2026_05_15_063932) ensures any
 * role that previously had a legacy permission also has its new-style twin.
 */
final class RolePermissions
{
    public static function assign(): void
    {
        self::setSuperadminPermissions();
        self::setOrganizationSuperadminPermissions();
        self::setOrganizationAdminPermissions();
    }

    public static function setSuperadminPermissions(): void
    {
        $superadmin = Role::findByName('Superadmin');

        $superadmin->givePermissionTo([
            // Landlord scope
            'admin.settings.create',
            'admin.settings.read',
            'admin.settings.update',
            'admin.settings.delete',
            'admin.permissions.create',
            'admin.permissions.read',
            'admin.permissions.update',
            'admin.permissions.delete',
            'admin.tenants.create',
            'admin.tenants.read',
            'admin.tenants.update',
            'admin.tenants.delete',
            'admin.users.analytics',
            'admin.users.impersonate',
            'admin.audit-trail.read',
            'admin.health.read',

            // Inherited tenant baseline (Superadmin acts cross-tenant too)
            'core.dashboard.access',
            'core.users.create',
            'core.users.read',
            'core.users.update',
            'core.users.delete',
            'core.roles.create',
            'core.roles.read',
            'core.roles.update',
            'core.roles.delete',
            'core.teams.create',
            'core.teams.read',
            'core.teams.update',
            'core.teams.delete',
            'core.communications.create',
            'core.communications.read',
            'core.communications.update',
            'core.communications.delete',
            'core.settings.manage',
            'core.api-keys.manage',
        ]);
    }

    public static function setOrganizationSuperadminPermissions(): void
    {
        $organizationSuperadmin = Role::findByName('Org Superadmin');

        $organizationSuperadmin->givePermissionTo([
            'core.dashboard.access',
            'core.users.create',
            'core.users.read',
            'core.users.update',
            'core.users.delete',
            'core.roles.create',
            'core.roles.read',
            'core.roles.update',
            'core.roles.delete',
            'core.communications.create',
            'core.communications.read',
            'core.communications.update',
            'core.communications.delete',
            'core.settings.manage',
            'core.api-keys.manage',
        ]);
    }

    public static function setOrganizationAdminPermissions(): void
    {
        $organizationAdmin = Role::findByName('Org Admin');

        $organizationAdmin->givePermissionTo([
            'core.dashboard.access',
            'core.users.create',
            'core.users.read',
            'core.users.update',
            'core.users.delete',
            'core.roles.create',
            'core.roles.read',
            'core.roles.update',
            'core.roles.delete',
            'core.communications.create',
            'core.communications.read',
            'core.communications.update',
            'core.communications.delete',
            'core.settings.manage',
            'core.api-keys.manage',
        ]);
    }
}
