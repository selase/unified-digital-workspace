<?php

declare(strict_types=1);

return [
    'name' => 'Monitoring & Evaluation',
    'slug' => 'monitoring-evaluation',
    'version' => '1.0.0',
    'description' => 'Programme indicators with baselines and targets, readings over time, automatic figures from other modules, progress against target, and funder-ready reports.',
    'namespace' => 'App\\Modules\\MonitoringEvaluation',
    'provider' => 'App\\Modules\\MonitoringEvaluation\\Providers\\MonitoringEvaluationServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core'],
    'conflicts_with' => [],
    'features' => [
        'monitoring-evaluation.manage' => [
            'type' => 'boolean',
            'name' => 'Monitoring & Evaluation',
            'description' => 'Indicators, targets and readings for programme results.',
        ],
    ],
    'permissions' => [
        'monitoring.view',
        'monitoring.manage',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
