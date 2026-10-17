<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class RealEstateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind singletons or services if needed
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'real-estate');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('real-estate')
            ->name('real-estate.')
            ->group(__DIR__.'/../Routes/web.php');

        // Public site routes for property listings and inquiry
        Route::middleware(['web'])
            ->prefix('site/properties')
            ->name('real-estate.public.')
            ->group(function (): void {
                $publicRoutes = __DIR__.'/../Routes/public.php';
                if (file_exists($publicRoutes)) {
                    require $publicRoutes;
                }
            });
    }
}
