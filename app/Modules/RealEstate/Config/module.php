<?php

declare(strict_types=1);

return [
    'name' => 'Real Estate & Land Compliance',
    'slug' => 'real-estate',
    'description' => 'Ghana Land Acquisition & Construction Compliance Lifecycle, Public Land Listings, Client Portal, Spatial Overlap Protection & Legacy Vault',
    'version' => '1.0.0',
    'provider' => App\Modules\RealEstate\Providers\RealEstateServiceProvider::class,
    'permissions' => [
        'realestate.view',
        'realestate.manage',
        'realestate.compliance.verify',
        'realestate.client.portal',
        'realestate.documents.manage',
    ],
];
