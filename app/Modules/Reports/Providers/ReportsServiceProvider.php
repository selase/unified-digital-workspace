<?php

declare(strict_types=1);

namespace App\Modules\Reports\Providers;

use App\Modules\Reports\Services\ReportDataSourceRegistry;
use App\Modules\Reports\Services\ReportGeneratorService;
use Illuminate\Support\ServiceProvider;

final class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReportDataSourceRegistry::class);
        $this->app->singleton(ReportGeneratorService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'reports');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])
            ->prefix('reports')
            ->name('reports.')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
