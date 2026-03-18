<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Providers;

use App\Modules\Concerns\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Base service provider for CMS site modules (tenant-specific custom applications).
 *
 * Site modules extend this class to get:
 * - Standard module infrastructure (models, migrations, commands, views)
 * - Public routes registered at both /site prefix and root level (custom domains)
 * - View resolution integration (site module views take highest priority)
 *
 * Usage: `php artisan cms:make-site {tenant-slug}` generates a module that extends this.
 */
abstract class CmsSiteServiceProvider extends ModuleServiceProvider
{
    /**
     * Get the tenant slug this site module is built for.
     */
    abstract public function getTenantSlug(): string;

    /**
     * Register routes: admin routes at /{module-slug}/ prefix,
     * public routes at /site prefix + root level for custom domains.
     */
    protected function registerRoutes(): void
    {
        parent::registerRoutes();

        $moduleSlug = $this->getModuleSlug();
        $publicRoutes = $this->getModulePath().'/Routes/public.php';

        if (! file_exists($publicRoutes)) {
            return;
        }

        // /site prefix — subdomain access
        Route::group([
            'middleware' => ['web', "module:{$moduleSlug}"],
            'prefix' => 'site',
            'as' => "{$moduleSlug}.public.",
        ], function () use ($publicRoutes): void {
            require $publicRoutes;
        });

        // Root-level — custom domain access
        Route::group([
            'middleware' => ['web', 'cms.website', "module:{$moduleSlug}"],
            'as' => "{$moduleSlug}.website.",
        ], function () use ($publicRoutes): void {
            require $publicRoutes;
        });
    }
}
