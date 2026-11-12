<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class ResultsFrameworkServiceProvider extends ModuleServiceProvider
{
    public function getModuleSlug(): string
    {
        return 'results-framework';
    }

    public function getModuleName(): string
    {
        return 'ResultsFramework';
    }

    protected function registerRoutes(): void
    {
        parent::registerRoutes();

        Route::get('/programme-results/{path?}', function (Request $request, ?string $path = null): RedirectResponse {
            $destination = '/results-framework'.($path === null ? '' : '/'.$path);
            $query = $request->getQueryString();

            return redirect()->to($destination.($query === null ? '' : '?'.$query), 301);
        })->where('path', '.*')->middleware(['web', 'auth']);
    }

    protected function bootModule(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
