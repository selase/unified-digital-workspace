<?php

declare(strict_types=1);

return [
    'name' => 'Grants & Funders',
    'slug' => 'grants',
    'version' => '1.0.0',
    'description' => 'Funders, grant agreements, expected and received instalments, reporting deadlines and how much of each grant has been spent. Instalments post to the Finance ledger against a restricted fund.',
    'namespace' => 'App\\Modules\\Grants',
    'provider' => 'App\\Modules\\Grants\\Providers\\GrantsServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core', 'finance'],
    'conflicts_with' => [],
    'features' => [
        'grants.manage' => [
            'type' => 'boolean',
            'name' => 'Grants & Funders',
            'description' => 'Grant agreements, instalments and funder reporting for the organisation.',
        ],
    ],
    'permissions' => [
        'grants.view',
        'grants.manage',
        'grants.record',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
