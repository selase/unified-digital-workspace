<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Module Information
    |--------------------------------------------------------------------------
    */
    'name' => 'Incident Management',
    'slug' => 'incident-management',
    'version' => '1.0.0',
    'description' => 'Incident management workflow with assignments, escalation, tasks, and reporting.',

    /*
    |--------------------------------------------------------------------------
    | Module Namespace & Provider
    |--------------------------------------------------------------------------
    */
    'namespace' => 'App\\Modules\\IncidentManagement',
    'provider' => 'App\\Modules\\IncidentManagement\\Providers\\IncidentManagementServiceProvider',

    /*
    |--------------------------------------------------------------------------
    | Pricing & Billing
    |--------------------------------------------------------------------------
    */
    'tier' => 'standard',
    'is_billable' => true,

    /*
    |--------------------------------------------------------------------------
    | Dependencies & Conflicts
    |--------------------------------------------------------------------------
    */
    'depends_on' => ['core'],
    'conflicts_with' => [],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'incidents.manage' => [
            'type' => 'boolean',
            'name' => 'Incident Management',
            'description' => 'Capture and resolve incidents with assignments and SLAs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'incidents.view',
        'incidents.create',
        'incidents.update',
        'incidents.delete',

        'incidents.assign',
        'incidents.delegate',
        'incidents.escalate',

        'incidents.tasks.manage',
        'incidents.comments.manage',

        'incidents.priorities.manage',
        'incidents.statuses.manage',
        'incidents.categories.manage',

        'incidents.public.submit',
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
