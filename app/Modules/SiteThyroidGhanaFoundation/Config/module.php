<?php

declare(strict_types=1);

return [
    'name' => 'SiteThyroidGhanaFoundation',
    'slug' => 'site-thyroid-ghana-foundation',
    'version' => '1.0.0',
    'description' => 'Custom CMS site for thyroid-ghana-foundation',

    'namespace' => 'App\\Modules\\SiteThyroidGhanaFoundation',
    'provider' => 'App\\Modules\\SiteThyroidGhanaFoundation\\Providers\\SiteThyroidGhanaFoundationServiceProvider',

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