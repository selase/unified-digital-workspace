<?php

declare(strict_types=1);

return [
    'demo' => 'demo10',

    'module_pages' => [
        'dashboard' => [
            'label' => 'Tenant Dashboard',
            'demo_page' => 'index.html',
            'route' => 'tenant.dashboard',
        ],
        'forums' => [
            'label' => 'Forums & Messaging',
            'demo_page' => 'network/get-started.html',
            'route' => 'forums.hub',
        ],
        'document-management' => [
            'label' => 'Document Management',
            'demo_page' => 'user-table/app-roster.html',
            'route' => null,
        ],
        'memos' => [
            'label' => 'Memos',
            'demo_page' => 'public-profile/projects/3-columns.html',
            'route' => null,
        ],
        'incident-management' => [
            'label' => 'Incident Management',
            'demo_page' => 'account/activity.html',
            'route' => null,
        ],
        'quality-monitoring' => [
            'label' => 'Quality Monitoring',
            'demo_page' => 'account/notifications.html',
            'route' => null,
        ],
        'cms' => [
            'label' => 'CMS',
            'demo_page' => 'apps/blog/add-post.html',
            'route' => null,
        ],
    ],
];
