<?php

declare(strict_types=1);

return [
    'name' => 'Donations & Donors',
    'slug' => 'donations',
    'version' => '1.0.0',
    'description' => 'Donors, campaigns and donations for foundations and NGOs. Every donation posts to the Finance ledger against its fund, and donors get numbered receipts.',
    'namespace' => 'App\\Modules\\Donations',
    'provider' => 'App\\Modules\\Donations\\Providers\\DonationsServiceProvider',
    'tier' => 'premium',
    'is_billable' => true,
    'depends_on' => ['core', 'finance'],
    'conflicts_with' => [],
    'features' => [
        'donations.manage' => [
            'type' => 'boolean',
            'name' => 'Donations & Donors',
            'description' => 'Donor records, campaigns, donations and receipts for the organisation.',
        ],
    ],
    'permissions' => [
        'donations.view',
        'donations.donors.manage',
        'donations.campaigns.manage',
        'donations.record',
    ],
    'routes' => [
        'web' => true,
        'api' => false,
    ],
    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
