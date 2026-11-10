<?php

declare(strict_types=1);

namespace App\Modules\Governance\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class GovernanceServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'governance';
    }

    public function getModuleName(): string
    {
        return 'Governance';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
