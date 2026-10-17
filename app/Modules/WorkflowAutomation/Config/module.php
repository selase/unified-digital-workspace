<?php

declare(strict_types=1);

return [
    'name' => 'Workflow & Process Automation',
    'slug' => 'workflow-automation',
    'description' => 'Cross-module event-driven business process automation, conditional actions, and recursion circuit breakers.',
    'version' => '1.0.0',
    'provider' => App\Modules\WorkflowAutomation\Providers\WorkflowAutomationServiceProvider::class,
    'permissions' => [
        'workflows.view',
        'workflows.manage',
        'workflows.execute',
    ],
];
