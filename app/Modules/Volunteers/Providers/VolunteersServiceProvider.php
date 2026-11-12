<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use App\Modules\Volunteers\Services\VolunteerResultMeasures;
use Illuminate\Support\Facades\Route;

final class VolunteersServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'volunteers';
    }

    public function getModuleName(): string
    {
        return 'Volunteers';
    }

    protected function registerBindings(): void
    {
        $this->app->tag([VolunteerResultMeasures::class], 'results.measure-providers');
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Route::middleware('web')->group(__DIR__.'/../Routes/public.php');
    }
}
