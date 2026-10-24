<?php

declare(strict_types=1);

return [
    'name' => 'SiteLifepartnersPlatform',
    'slug' => 'site-lifepartners-platform',
    'version' => '1.0.0',
    'description' => 'Custom CMS site for lifepartners-platform',

    'namespace' => 'App\\Modules\\SiteLifepartnersPlatform',
    'provider' => 'App\\Modules\\SiteLifepartnersPlatform\\Providers\\SiteLifepartnersPlatformServiceProvider',

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
