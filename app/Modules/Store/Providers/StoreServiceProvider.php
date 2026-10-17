<?php

declare(strict_types=1);

namespace App\Modules\Store\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'store');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web'])
            ->prefix('store')
            ->name('store.')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
