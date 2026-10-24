<?php

declare(strict_types=1);

return [
    'name' => 'SiteKunneo',
    'slug' => 'site-kunneo',
    'version' => '1.0.0',
    'description' => 'Custom CMS site for kunneo',

    'namespace' => 'App\\Modules\\SiteKunneo',
    'provider' => 'App\\Modules\\SiteKunneo\\Providers\\SiteKunneoServiceProvider',

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