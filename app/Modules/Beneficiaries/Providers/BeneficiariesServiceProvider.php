<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class BeneficiariesServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'beneficiaries';
    }

    public function getModuleName(): string
    {
        return 'Beneficiaries';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
