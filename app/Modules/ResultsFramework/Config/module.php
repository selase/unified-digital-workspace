<?php

declare(strict_types=1);

return [
    'name' => 'Results Framework',
    'slug' => 'results-framework',
    'version' => '1.0.0',
    'description' => 'Track organisational results with indicators, baselines, targets, readings and reports.',
    'namespace' => 'App\\Modules\\ResultsFramework',
    'provider' => 'App\\Modules\\ResultsFramework\\Providers\\ResultsFrameworkServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core'],
    'conflicts_with' => [],
    'features' => [
        'results-framework.manage' => [
            'type' => 'boolean',
            'name' => 'Results Framework',
            'description' => 'Indicators, targets and readings for programme results.',
        ],
    ],
    'permissions' => [
        'results.view',
        'results.manage',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
