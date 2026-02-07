<?php

declare(strict_types=1);

return [
    'name' => 'Document Management',
    'slug' => 'document-management',
    'version' => '1.0.0',
    'description' => 'Tenant document management with versions, sharing, quizzes, and audits.',

    'namespace' => 'App\\Modules\\DocumentManagement',
    'provider' => 'App\\Modules\\DocumentManagement\\Providers\\DocumentManagementServiceProvider',

    'tier' => 'standard',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'documents.manage' => [
            'type' => 'boolean',
            'name' => 'Document Management',
            'description' => 'Manage documents with versions, visibility, and quizzes.',
        ],
    ],

    'permissions' => [
        'documents.view',
        'documents.create',
        'documents.update',
        'documents.delete',
        'documents.publish',
        'documents.manage_quizzes',
        'documents.manage_versions',
        'documents.audit.view',
    ],

    'routes' => [
        'web' => true,
        'api' => true,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
