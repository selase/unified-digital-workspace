<?php

declare(strict_types=1);

namespace App\Modules\Forms\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Views', 'forms');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web'])
            ->prefix('forms')
            ->name('forms.')
            ->group(__DIR__.'/../Routes/web.php');
    }
}
