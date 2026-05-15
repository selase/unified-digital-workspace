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

    // SaaS-level features the tenant's package must include before this
    // module can be enabled. Custom site modules publish content under a
    // tenant-owned domain, so custom-domains is the gate.
    'required_features' => ['custom-domains'],

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
