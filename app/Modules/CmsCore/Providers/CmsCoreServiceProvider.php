<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Providers;

use App\Modules\CmsCore\Console\Commands\GenerateSitemapCommand;
use App\Modules\CmsCore\Console\Commands\MakeSiteCommand;
use App\Modules\CmsCore\Services\CmsUrlService;
use App\Modules\Concerns\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

final class CmsCoreServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the module slug.
     */
    public function getModuleSlug(): string
    {
        return 'cms-core';
    }

    /**
     * Get the module name (directory name).
     */
    public function getModuleName(): string
    {
        return 'CmsCore';
    }

    protected function registerBindings(): void
    {
        $this->app->singleton(CmsUrlService::class);

        // Share CMS services with all CMS views and site module views
        View::composer(['cms-core::*', 'site-*::*'], function ($view): void {
            $view->with('cmsUrl', $this->app->make(CmsUrlService::class));
        });
    }

    protected function registerCommands(): void
    {
        $this->commands([
            GenerateSitemapCommand::class,
            MakeSiteCommand::class,
        ]);
    }

    /**
     * Register module routes: admin, public (/site prefix), website (root for custom domains),
     * and deferred catch-all for static pages.
     */
    protected function registerRoutes(): void
    {
        parent::registerRoutes();

        $moduleSlug = $this->getModuleSlug();
        $publicRoutes = $this->getModulePath().'/Routes/public.php';

        if (file_exists($publicRoutes)) {
            // /site prefix — subdomain access (backward compatible)
            Route::group([
                'middleware' => ['web', "module:{$moduleSlug}"],
                'prefix' => 'site',
                'as' => "{$moduleSlug}.public.",
            ], function () use ($publicRoutes): void {
                require $publicRoutes;
            });

            // Root-level — custom domain access only
            Route::group([
                'middleware' => ['web', 'cms.website'],
                'as' => "{$moduleSlug}.website.",
            ], function () use ($publicRoutes): void {
                require $publicRoutes;
            });
        }
    }
}
