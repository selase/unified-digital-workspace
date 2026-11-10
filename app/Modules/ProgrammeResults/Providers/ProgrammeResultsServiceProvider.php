<?php

declare(strict_types=1);

namespace App\Modules\ProgrammeResults\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class ProgrammeResultsServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'programme-results';
    }

    public function getModuleName(): string
    {
        return 'ProgrammeResults';
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
