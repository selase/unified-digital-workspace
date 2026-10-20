<?php

declare(strict_types=1);

return [
    'reviewed_on' => '2026-10-20',
    'description' => 'Recommended retail prices; independent GHS and USD prices, not live exchange conversions. No request or background-job charges.',
    'active_users' => [
        'community' => ['USD' => 0, 'GHS' => 0],
        'website' => ['USD' => 1, 'GHS' => 10],
        'team' => ['USD' => 2, 'GHS' => 25],
        'institution' => ['USD' => 4, 'GHS' => 50],
        'marketplace' => ['USD' => 7, 'GHS' => 85],
    ],
    'meters' => [
        'emails.count' => ['unit_quantity' => 1000, 'USD' => 1.00, 'GHS' => 15.00],
        'storage.bytes' => ['unit_quantity' => 1073741824, 'USD' => 0.10, 'GHS' => 1.50],
        'db.bytes' => ['unit_quantity' => 1073741824, 'USD' => 0.20, 'GHS' => 3.00],
    ],
    'hybrid' => [
        'status' => 'opt_in',
        'website_plans' => [
            'essentials' => [
                'name' => 'Website Essentials', 'GHS' => 300.00, 'USD' => 25.00,
                'setup' => ['GHS' => 500.00, 'USD' => 50.00],
                'sites' => 1, 'editors' => 2, 'domains' => 1,
                'files_gib' => 5, 'database_gib' => 0.5, 'bandwidth_gib' => 25,
                'support' => 'Email support for platform faults; content edits are quoted separately.',
            ],
            'managed_business' => [
                'name' => 'Managed Business', 'GHS' => 600.00, 'USD' => 50.00,
                'setup' => ['GHS' => 1000.00, 'USD' => 100.00],
                'sites' => 1, 'editors' => 5, 'domains' => 1,
                'files_gib' => 20, 'database_gib' => 2, 'bandwidth_gib' => 100,
                'support' => 'Email support plus 30 minutes of routine configuration help per month; content edits are quoted separately.',
            ],
        ],
        'extra_editor' => ['GHS' => 25.00, 'USD' => 2.00],
        'access' => [
            'self_service' => ['name' => 'Self-service', 'GHS' => 5.00, 'USD' => 0.50],
            'operational' => ['name' => 'Operational', 'GHS' => 25.00, 'USD' => 2.00],
        ],
        'bands' => [
            ['up_to' => 100, 'discount_percent' => 0],
            ['up_to' => 500, 'discount_percent' => 10],
            ['up_to' => 1500, 'discount_percent' => 20],
            ['up_to' => null, 'discount_percent' => 30],
        ],
        'included_modules' => ['core', 'cms-core', 'forms'],
        'packages' => [
            'website' => ['name' => 'Intranet', 'GHS' => 100.00, 'USD' => 10.00, 'modules' => ['intranet'], 'features' => []],
            'collaboration' => ['name' => 'Collaboration & Documents', 'GHS' => 500.00, 'USD' => 40.00, 'modules' => ['document-management', 'memos', 'forums', 'project-management', 'reports', 'workflow-automation'], 'features' => []],
            'crm' => ['name' => 'CRM', 'GHS' => 500.00, 'USD' => 40.00, 'modules' => ['crm'], 'features' => []],
            'hr' => ['name' => 'HR Management', 'GHS' => 1000.00, 'USD' => 80.00, 'modules' => ['hrms-core'], 'features' => []],
            'quality' => ['name' => 'Quality & Safety', 'GHS' => 1000.00, 'USD' => 80.00, 'modules' => ['incident-management', 'quality-monitoring'], 'features' => []],
            'facilities' => ['name' => 'Facilities', 'GHS' => 500.00, 'USD' => 40.00, 'modules' => ['facilities-management'], 'features' => []],
            'commerce' => ['name' => 'Commerce', 'GHS' => 750.00, 'USD' => 60.00, 'modules' => ['store', 'vendor-marketplace'], 'features' => ['commerce']],
            'real_estate' => ['name' => 'Real Estate', 'GHS' => 500.00, 'USD' => 40.00, 'modules' => ['real-estate'], 'features' => []],
        ],
    ],
];
