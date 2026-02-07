<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Providers;

use App\Console\Commands\IncidentsCheckSla;
use App\Console\Commands\IncidentsDispatchReminders;
use App\Console\Commands\IncidentsGenerateReminders;
use App\Modules\Concerns\ModuleServiceProvider;

final class IncidentManagementServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the module slug.
     */
    public function getModuleSlug(): string
    {
        return 'incident-management';
    }

    /**
     * Get the module name (directory name).
     */
    public function getModuleName(): string
    {
        return 'IncidentManagement';
    }

    protected function registerCommands(): void
    {
        $this->commands([
            IncidentsCheckSla::class,
            IncidentsDispatchReminders::class,
            IncidentsGenerateReminders::class,
        ]);
    }
}
