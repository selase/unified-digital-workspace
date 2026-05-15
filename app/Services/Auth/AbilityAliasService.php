<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Maps the legacy "verb noun" permission convention onto the new
 * module.action.scope style used by every other module in this app.
 *
 * During the transition window (Phase 3.1–3.4 of the entitlement
 * cleanup), both names resolve correctly:
 *
 *   - Code that still uses `can('read user')` continues to work because
 *     the permission row 'read user' exists in the database.
 *   - Code that uses the new `can('core.users.read')` works because
 *     AuthServiceProvider registers a Gate::before that translates it
 *     back to 'read user' for the underlying permission check.
 *
 * Once all call sites are updated to the new names (Phase 3.3) and the
 * cutover migration renames the rows (Phase 3.4), this alias map keeps
 * working as a safety net for any caller that slips through.
 */
final class AbilityAliasService
{
    /**
     * Canonical mapping: new module.action.scope -> legacy verb noun.
     *
     * Tenant-baseline permissions are owned by the Core module.
     * Landlord-only permissions live under the `admin.*` namespace.
     *
     * @var array<string, string>
     */
    private const NEW_TO_LEGACY = [
        // Core (tenant baseline)
        'core.dashboard.access' => 'access dashboard',
        'core.users.read' => 'read user',
        'core.users.create' => 'create user',
        'core.users.update' => 'update user',
        'core.users.delete' => 'delete user',
        'core.roles.read' => 'read role',
        'core.roles.create' => 'create role',
        'core.roles.update' => 'update role',
        'core.roles.delete' => 'delete role',
        'core.teams.read' => 'read team',
        'core.teams.create' => 'create team',
        'core.teams.update' => 'update team',
        'core.teams.delete' => 'delete team',
        'core.communications.read' => 'read communication',
        'core.communications.create' => 'create communication',
        'core.communications.update' => 'update communication',
        'core.communications.delete' => 'delete communication',
        'core.settings.manage' => 'manage organization settings',
        'core.api-keys.manage' => 'manage api keys',

        // Admin (landlord scope)
        'admin.tenants.read' => 'read tenant',
        'admin.tenants.create' => 'create tenant',
        'admin.tenants.update' => 'update tenant',
        'admin.tenants.delete' => 'delete tenant',
        'admin.users.impersonate' => 'impersonate user',
        'admin.users.analytics' => 'user analytics',
        'admin.audit-trail.read' => 'read audit-trail',
        'admin.health.read' => 'read application health',
        'admin.settings.create' => 'create setting',
        'admin.settings.read' => 'read setting',
        'admin.settings.update' => 'update setting',
        'admin.settings.delete' => 'delete setting',
        'admin.permissions.create' => 'create permission',
        'admin.permissions.read' => 'read permission',
        'admin.permissions.update' => 'update permission',
        'admin.permissions.delete' => 'delete permission',
    ];

    /**
     * Returns the legacy permission name for a new-style ability,
     * or null if the ability isn't aliased.
     */
    public static function toLegacy(string $ability): ?string
    {
        return self::NEW_TO_LEGACY[$ability] ?? null;
    }

    /**
     * Returns the new-style ability name for a legacy permission,
     * or null if no mapping exists.
     */
    public static function toNew(string $legacyName): ?string
    {
        $flipped = array_flip(self::NEW_TO_LEGACY);

        return $flipped[$legacyName] ?? null;
    }

    /**
     * @return array<string, string>  new -> legacy
     */
    public static function all(): array
    {
        return self::NEW_TO_LEGACY;
    }

    /**
     * Heuristic: a module-style ability contains a dot.
     */
    public static function isNewStyle(string $ability): bool
    {
        return str_contains($ability, '.');
    }
}
