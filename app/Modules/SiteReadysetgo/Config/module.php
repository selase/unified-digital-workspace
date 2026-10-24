<?php

declare(strict_types=1);

return [
    'name' => 'SiteReadysetgo',
    'slug' => 'site-readysetgo',
    'version' => '1.0.0',
    'description' => 'Custom CMS site for readysetgo',

    'namespace' => 'App\\Modules\\SiteReadysetgo',
    'provider' => 'App\\Modules\\SiteReadysetgo\\Providers\\SiteReadysetgoServiceProvider',

    'tier' => 'custom',
    'is_billable' => false,

    'depends_on' => ['cms-core'],
    'conflicts_with' => [],

    'features' => [],
    'permissions' => [],

    'routes' => [
        'web' => true,
        'api' => false,
        'public' => true,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];