<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fundamental Modules
    |--------------------------------------------------------------------------
    |
    | Modules listed here are automatically enabled for every tenant at
    | creation time via App\Observers\TenantObserver. They represent the
    | baseline functionality every tenant has access to regardless of plan.
    |
    | Optional modules (cms-core, hrms-core, etc.) are NOT listed here —
    | tenants opt into them through the modules catalog or via plan tier.
    |
    */
    'fundamental' => [
        'core',
    ],
];
