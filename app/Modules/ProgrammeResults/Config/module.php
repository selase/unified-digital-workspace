<?php

declare(strict_types=1);

return [
    'name' => 'NGO M&E',
    'slug' => 'programme-results',
    'version' => '1.0.0',
    'description' => 'A simple results tracker for NGOs and funders: indicators with baselines and targets, readings over time, figures worked out from Donations, Volunteers and Beneficiaries, and funder-ready reports. For workplans with approvals, use Quality Monitoring.',
    'namespace' => 'App\\Modules\\ProgrammeResults',
    'provider' => 'App\\Modules\\ProgrammeResults\\Providers\\ProgrammeResultsServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core'],
    'conflicts_with' => [],
    'features' => [
        'programme-results.manage' => [
            'type' => 'boolean',
            'name' => 'NGO M&E',
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
