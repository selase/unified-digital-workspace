<?php

declare(strict_types=1);

namespace App\Modules\Donations\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class DonationsServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'donations';
    }

    public function getModuleName(): string
    {
        return 'Donations';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
