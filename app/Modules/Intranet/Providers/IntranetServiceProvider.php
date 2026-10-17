<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IntranetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'intranet');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('intranet')
            ->name('intranet.')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
