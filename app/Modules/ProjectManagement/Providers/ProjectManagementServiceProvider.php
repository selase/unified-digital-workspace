<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Providers;

use App\Modules\Concerns\ModuleServiceProvider;

final class ProjectManagementServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'project-management';
    }

    public function getModuleName(): string
    {
        return 'ProjectManagement';
    }
}
