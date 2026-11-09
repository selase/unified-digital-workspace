<?php

declare(strict_types=1);

namespace App\Modules\Grants\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

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

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
