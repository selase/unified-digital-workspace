<?php

declare(strict_types=1);

return [
    'name' => 'Volunteers',
    'slug' => 'volunteers',
    'version' => '1.0.0',
    'description' => 'Volunteer sign-up, records, opportunities with limited places, hours logged and approved, and recognition totals.',
    'namespace' => 'App\\Modules\\Volunteers',
    'provider' => 'App\\Modules\\Volunteers\\Providers\\VolunteersServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core'],
    'conflicts_with' => [],
    'features' => [
        'volunteers.manage' => [
            'type' => 'boolean',
            'name' => 'Volunteers',
            'description' => 'Volunteer records, opportunities and hours for the organisation.',
        ],
    ],
    'permissions' => [
        'volunteers.view',
        'volunteers.manage',
        'volunteers.hours.approve',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
