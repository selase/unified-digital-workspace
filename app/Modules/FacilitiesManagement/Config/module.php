<?php

declare(strict_types=1);

return [
    'name' => 'Facilities Management',
    'slug' => 'facilities-management',
    'description' => 'Dual-Mode Facilities Management: Institutional Asset Lifecycle & Preventive Maintenance (Mode A) + Estate Occupant Artisan & Vendor Marketplace Requests (Mode B)',
    'version' => '1.0.0',
    'provider' => App\Modules\FacilitiesManagement\Providers\FacilitiesManagementServiceProvider::class,
    'permissions' => [
        'facilities.view',
        'facilities.manage',
        'facilities.assets.manage',
        'facilities.maintenance.manage',
        'facilities.occupant.request',
    ],
];
