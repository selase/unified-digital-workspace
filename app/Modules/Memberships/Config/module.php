<?php

declare(strict_types=1);

return [
    'name' => 'Memberships',
    'slug' => 'memberships',
    'version' => '1.0.0',
    'description' => 'Membership types and fees, member records, dues invoiced through Finance, renewals, lapses and a public join page.',
    'namespace' => 'App\\Modules\\Memberships',
    'provider' => 'App\\Modules\\Memberships\\Providers\\MembershipsServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core', 'finance'],
    'conflicts_with' => [],
    'features' => [
        'memberships.manage' => [
            'type' => 'boolean',
            'name' => 'Memberships',
            'description' => 'Member records, membership types and dues for the organisation.',
        ],
    ],
    'permissions' => [
        'memberships.view',
        'memberships.manage',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
