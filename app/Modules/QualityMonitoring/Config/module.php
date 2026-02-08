<?php

declare(strict_types=1);

return [
    'name' => 'Quality Monitoring',
    'slug' => 'quality-monitoring',
    'version' => '1.0.0',
    'description' => 'Monitoring, evaluation, and performance workplans with approvals and reporting.',

    'namespace' => 'App\\Modules\\QualityMonitoring',
    'provider' => 'App\\Modules\\QualityMonitoring\\Providers\\QualityMonitoringServiceProvider',

    'tier' => 'standard',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'qm.manage' => [
            'type' => 'boolean',
            'name' => 'Quality Monitoring',
            'description' => 'Manage MEP workplans, approvals, and monitoring.',
        ],
    ],

    'permissions' => [
        'qm.workplans.view',
        'qm.workplans.manage',
        'qm.approvals.manage',
        'qm.kpis.manage',
        'qm.reviews.manage',
        'qm.alerts.manage',
        'qm.variances.manage',
    ],

    'routes' => [
        'web' => true,
        'api' => true,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
