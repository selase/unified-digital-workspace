<?php

declare(strict_types=1);

namespace App\Modules\Grants\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\Grants\Services\GrantResultMeasures;

final class GrantsServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'grants';
    }

    public function getModuleName(): string
    {
        return 'Grants';
    }

    protected function registerBindings(): void
    {
        $this->app->tag([GrantResultMeasures::class], 'results.measure-providers');
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
