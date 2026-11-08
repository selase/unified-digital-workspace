<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\HrmsCore\Services\ApprovalWorkflowService;
use App\Modules\HrmsCore\Services\EmployeeService;
use App\Modules\HrmsCore\Services\LeaveAccrualService;
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
        $this->app->singleton(LeaveAccrualService::class);
        $this->app->singleton(ApprovalWorkflowService::class);
    }

    /**
     * Register module commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \App\Modules\HrmsCore\Console\Commands\HrmsLeaveRolloverCommand::class,
        ]);
    }

    /**
     * Adds the public careers page next to the authenticated module routes: under /site for
     * subdomain access and at the root for custom domains, as the CMS public pages do.
     */
    protected function registerRoutes(): void
    {
        parent::registerRoutes();

        $publicRoutes = $this->getModulePath().'/Routes/public.php';

        \Illuminate\Support\Facades\Route::group([
            'middleware' => ['web', 'module:hrms-core'],
            'prefix' => 'site',
            'as' => 'hrms-core.public.',
        ], fn () => require $publicRoutes);

        \Illuminate\Support\Facades\Route::group([
            'middleware' => ['web', 'cms.website', 'module:hrms-core'],
            'as' => 'hrms-core.website.',
        ], fn () => require $publicRoutes);
    }
}
