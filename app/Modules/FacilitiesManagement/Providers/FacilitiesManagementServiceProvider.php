<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FacilitiesManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'facilities-management');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\FacilitiesManagement\Console\Commands\GeneratePreventiveWorkOrdersCommand::class,
            ]);
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('facilities-management')
            ->name('facilities-management.')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
