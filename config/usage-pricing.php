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
];
