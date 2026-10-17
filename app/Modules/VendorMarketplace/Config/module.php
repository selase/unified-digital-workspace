<?php

declare(strict_types=1);

return [
    'name' => 'Vendor Marketplace & Escrow',
    'slug' => 'vendor-marketplace',
    'version' => '1.0.0',
    'description' => 'Unified service provider & artisan marketplace, proforma invoicing, double-entry escrow ledger, dispute resolution, and 3-way commission routing.',

    'namespace' => 'App\\Modules\\VendorMarketplace',
    'provider' => 'App\\Modules\\VendorMarketplace\\Providers\\VendorMarketplaceServiceProvider',

    'tier' => 'enterprise',
    'is_billable' => true,

    'depends_on' => ['core'],
    'conflicts_with' => [],

    'features' => [
        'vendor-marketplace.manage' => [
            'type' => 'boolean',
            'name' => 'Vendor Marketplace & Escrow Settlement',
            'description' => 'Onboard service providers, issue proforma invoices, hold escrow funds, and route 3-way commissions.',
        ],
    ],

    'permissions' => [
        'vendors.view',
        'vendors.manage',
        'vendors.portal.access',
        'escrow.manage',
        'escrow.disputes.resolve',
        'commissions.configure',
    ],

    'routes' => [
        'web' => true,
        'api' => false,
    ],

    'author' => 'UDW Team',
    'homepage' => null,
    'support' => null,
];
