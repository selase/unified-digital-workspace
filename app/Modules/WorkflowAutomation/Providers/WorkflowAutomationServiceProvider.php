<?php

declare(strict_types=1);

namespace App\Modules\WorkflowAutomation\Providers;

use App\Modules\WorkflowAutomation\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WorkflowAutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkflowEngineService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'workflow-automation');

        \Illuminate\Support\Facades\Event::listen(
            \App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent::class,
            \App\Modules\WorkflowAutomation\Listeners\WorkflowDomainEventListener::class
        );

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('workflows')
            ->name('workflow-automation.')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
