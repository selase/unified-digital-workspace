<?php

declare(strict_types=1);

return [
    'name' => 'Forums & Messaging',
    'slug' => 'forums',
    'version' => '1.0.0',
    'description' => 'Tenant forums with channels, threads, reactions, moderation, and messaging.',

    'namespace' => 'App\\Modules\\Forums',
    'provider' => 'App\\Modules\\Forums\\Providers\\ForumsServiceProvider',

    'tier' => 'standard',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'forums.access' => [
            'type' => 'boolean',
            'name' => 'Forums & Messaging',
            'description' => 'Access to discussion forums and messaging.',
        ],
    ],

    'permissions' => [
        'forums.view',
        'forums.post',
        'forums.moderate',
        'forums.messages.send',
        'forums.messages.manage',
    ],

    'permission_category' => 'forums',

    'routes' => [
        'web' => true,
        'api' => true,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
