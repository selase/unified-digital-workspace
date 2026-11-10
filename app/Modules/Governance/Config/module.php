<?php

declare(strict_types=1);

return [
    'name' => 'Board & Governance',
    'slug' => 'governance',
    'version' => '1.0.0',
    'description' => 'Board and committee members with terms, meetings with agendas, attendance and quorum, minutes with approval, resolutions, and action items with owners and due dates.',
    'namespace' => 'App\\Modules\\Governance',
    'provider' => 'App\\Modules\\Governance\\Providers\\GovernanceServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core'],
    'conflicts_with' => [],
    'features' => [
        'governance.manage' => [
            'type' => 'boolean',
            'name' => 'Board & Governance',
            'description' => 'Board members, meetings, minutes, resolutions and actions.',
        ],
    ],
    'permissions' => [
        'governance.view',
        'governance.manage',
        'governance.minutes.approve',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
