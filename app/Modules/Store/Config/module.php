<?php

declare(strict_types=1);

return [
    'name' => 'Stores & Marketplace',
    'slug' => 'store',
    'description' => 'Two-tier store: Platform quota store (SMS, Email, AI credits) and per-tenant internal staff/client store.',
    'version' => '1.0.0',
    'provider' => App\Modules\Store\Providers\StoreServiceProvider::class,
    'permissions' => [
        'store.view',
        'store.manage',
        'store.platform.purchase',
        'store.orders.manage',
    ],
];
