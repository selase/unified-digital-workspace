<?php

declare(strict_types=1);

namespace App\Modules\Crm\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class CrmServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'crm';
    }

    public function getModuleName(): string
    {
        return 'Crm';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
