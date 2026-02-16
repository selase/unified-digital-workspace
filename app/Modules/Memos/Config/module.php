<?php

declare(strict_types=1);

return [
    'name' => 'Memos',
    'slug' => 'memos',
    'version' => '1.0.0',
    'description' => 'Secure memo circulation with signatures, minutes, and acknowledgements.',

    'namespace' => 'App\\Modules\\Memos',
    'provider' => 'App\\Modules\\Memos\\Providers\\MemosServiceProvider',

    'tier' => 'standard',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'memos.manage' => [
            'type' => 'boolean',
            'name' => 'Memo Management',
            'description' => 'Create, send, and track secure memos.',
        ],
    ],

    'permissions' => [
        'memos.view',
        'memos.create',
        'memos.update',
        'memos.delete',
        'memos.send',
        'memos.sign',
        'memos.acknowledge',
        'memos.minute',
        'memos.share',
        'memos.actions.manage',
    ],

    'routes' => [
        'web' => true,
        'api' => true,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
