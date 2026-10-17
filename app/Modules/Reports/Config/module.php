<?php

declare(strict_types=1);

return [
    'name' => 'Reports & Analytics',
    'slug' => 'reports',
    'description' => 'Cross-module custom report builder, dynamic filtering, and streaming CSV/PDF exports.',
    'version' => '1.0.0',
    'provider' => App\Modules\Reports\Providers\ReportsServiceProvider::class,
    'permissions' => [
        'reports.view',
        'reports.create',
        'reports.export',
        'reports.delete',
    ],
];
