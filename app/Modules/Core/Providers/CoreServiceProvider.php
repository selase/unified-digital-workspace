<?php

declare(strict_types=1);

namespace App\Modules\Core\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class CoreServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'core';
    }

    public function getModuleName(): string
    {
        return 'Core';
    }

    protected function registerBindings(): void
    {
        // Register Core module specific bindings here
    }

    protected function registerCommands(): void
    {
        // Register Core module specific commands here
    }
}
