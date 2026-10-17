<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class VendorMarketplaceServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'vendor-marketplace';
    }

    public function getModuleName(): string
    {
        return 'VendorMarketplace';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
