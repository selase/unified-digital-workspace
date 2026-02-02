<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\HrmsCore\Services\ApprovalWorkflowService;
use App\Modules\HrmsCore\Services\EmployeeService;
use App\Modules\HrmsCore\Services\LeaveCalculationService;

final class HrmsCoreServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the module slug.
     */
    public function getModuleSlug(): string
    {
        return 'hrms-core';
    }

    /**
     * Get the module name (directory name).
     */
    public function getModuleName(): string
    {
        return 'HrmsCore';
    }

    /**
     * Register module bindings.
     */
    protected function registerBindings(): void
    {
        $this->app->singleton(EmployeeService::class);
        $this->app->singleton(LeaveCalculationService::class);
        $this->app->singleton(ApprovalWorkflowService::class);
    }

    /**
     * Register module commands.
     */
    protected function registerCommands(): void
    {
        // Future: Register HRMS-specific commands here
    }
}
