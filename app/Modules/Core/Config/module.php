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
    | Permissions defined by this module. These are seeded into the
    | permissions table.
    */
    'permissions' => [
        'core.dashboard.view',
        'core.settings.view',
        'core.settings.update',
        'core.users.view',
        'core.users.create',
        'core.users.update',
        'core.users.delete',
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
