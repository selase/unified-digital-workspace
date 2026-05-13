<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Information
    |--------------------------------------------------------------------------
    */
    'name' => 'Core',
    'slug' => 'core',
    'version' => '1.0.0',
    'description' => 'Core module providing foundational functionality. Always enabled.',

    /*
    |--------------------------------------------------------------------------
    | Module Namespace & Provider
    |--------------------------------------------------------------------------
    */
    'namespace' => 'App\\Modules\\Core',
    'provider' => 'App\\Modules\\Core\\Providers\\CoreServiceProvider',

    /*
    |--------------------------------------------------------------------------
    | Pricing & Billing
    |--------------------------------------------------------------------------
    */
    'tier' => 'free',
    'is_billable' => false,

    /*
    |--------------------------------------------------------------------------
    | Dependencies & Conflicts
    |--------------------------------------------------------------------------
    */
    'depends_on' => [],
    'conflicts_with' => [],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | Features provided by this module. These are synced to the tenant's
    | feature table when the module is enabled.
    */
    'features' => [
        'core.dashboard' => [
            'type' => 'boolean',
            'name' => 'Dashboard Access',
            'description' => 'Access to the main dashboard',
        ],
        'core.settings' => [
            'type' => 'boolean',
            'name' => 'Settings Management',
            'description' => 'Ability to manage tenant settings',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    | The Core module owns the baseline tenant permissions every tenant Org
    | Superadmin / Org Admin needs to operate. Names use the existing legacy
    | "verb noun" convention rather than module.action.scope, since the gates
    | throughout app/Http/Controllers reference them as-is — renaming would
    | be a separate, larger migration.
    |
    | Module-specific permissions (e.g. cms.posts.view, hrms.employees.create)
    | live in their own modules and union with this baseline at runtime via
    | EntitlementService::getAllowedPermissionsForTenant().
    */
    'permissions' => [
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
        'manage api keys',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'web' => true,
        'api' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Metadata
    |--------------------------------------------------------------------------
    */
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
