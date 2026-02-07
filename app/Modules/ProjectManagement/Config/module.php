<?php

declare(strict_types=1);

return [
    'name' => 'Project Management',
    'slug' => 'project-management',
    'version' => '1.0.0',
    'description' => 'Tenant-scoped project management with tasks, dependencies, time tracking, and allocations.',

    'namespace' => 'App\\Modules\\ProjectManagement',
    'provider' => 'App\\Modules\\ProjectManagement\\Providers\\ProjectManagementServiceProvider',

    'tier' => 'standard',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'projects.manage' => [
            'type' => 'boolean',
            'name' => 'Project Management',
            'description' => 'Manage projects, milestones, tasks, and time tracking.',
        ],
    ],

    'permissions' => [
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'projects.tasks.manage',
        'projects.milestones.manage',
        'projects.dependencies.manage',
        'projects.time.manage',
        'projects.allocations.manage',
        'projects.members.manage',
        'projects.attachments.manage',
    ],

    'routes' => [
        'web' => true,
        'api' => true,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
