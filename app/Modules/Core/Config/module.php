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
    | The Core module owns the baseline tenant permissions every tenant
    | Org Superadmin / Org Admin needs to operate. Names follow the
    | module.action.scope convention. Legacy "verb noun" names still
    | exist in the database for back-compat and resolve via
    | App\Services\Auth\AbilityAliasService.
    */
    'permissions' => [
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
