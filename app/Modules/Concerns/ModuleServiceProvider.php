<?php

declare(strict_types=1);

namespace App\Modules\Concerns;

use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Get the module slug.
     */
    abstract public function getModuleSlug(): string;

    /**
     * Get the module name (directory name).
     */
    abstract public function getModuleName(): string;

    /**
     * Get the module base path.
     */
    final public function getModulePath(): string
    {
        return app_path("Modules/{$this->getModuleName()}");
    }

    /**
     * Register module services.
     */
    final public function register(): void
    {
        $this->mergeConfigFrom(
            $this->getModulePath().'/Config/module.php',
            "modules.{$this->getModuleSlug()}"
        );

        $this->registerBindings();
    }

    /**
     * Bootstrap module services.
     */
    final public function boot(): void
    {
        // Always register migrations for artisan commands
        $this->registerMigrations();

        // Register routes for all modules; middleware enforces enablement
        $this->registerRoutes();

        // Only load views/translations if module is enabled for the request
        if ($this->shouldBootForRequest()) {
            $this->registerViews();
            $this->registerTranslations();
        }

        // Register commands regardless of tenant context
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishing();
        }
    }

    /**
     * Check if the module should boot for the current request.
     */
    protected function shouldBootForRequest(): bool
    {
        // Core module is always enabled
        if ($this->getModuleSlug() === 'core') {
            return true;
        }

        // Check if we have a tenant context
        if (! $this->app->bound(TenantContext::class)) {
            return false;
        }

        $tenantContext = $this->app->make(TenantContext::class);
        $tenant = $tenantContext->getTenant();

        if (! $tenant) {
            return false;
        }

        // Check if module is enabled for this tenant
        $moduleManager = $this->app->make(ModuleManager::class);

        return $moduleManager->isEnabledForTenant($this->getModuleSlug(), $tenant);
    }

    /**
     * Register module bindings.
     */
    protected function registerBindings(): void
    {
        // Override in child classes to register module-specific bindings
    }

    /**
     * Register module migrations.
     */
    protected function registerMigrations(): void
    {
        $migrationsPath = $this->getModulePath().'/Database/Migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Register module routes.
     */
    protected function registerRoutes(): void
    {
        $moduleSlug = $this->getModuleSlug();
        $modulePath = $this->getModulePath();

        // Web routes
        $webRoutes = "{$modulePath}/Routes/web.php";
        if (file_exists($webRoutes)) {
            Route::group([
                'middleware' => ['web', 'auth', "module:{$moduleSlug}"],
                'prefix' => $moduleSlug,
                'as' => "{$moduleSlug}.",
            ], function () use ($webRoutes): void {
                require $webRoutes;
            });
        }

        // API routes
        $apiRoutes = "{$modulePath}/Routes/api.php";
        if (file_exists($apiRoutes)) {
            Route::group([
                'middleware' => ['api', 'auth:sanctum', "module:{$moduleSlug}"],
                'prefix' => "api/{$moduleSlug}",
                'as' => "api.{$moduleSlug}.",
            ], function () use ($apiRoutes): void {
                require $apiRoutes;
            });
        }
    }

    /**
     * Register module views.
     */
    protected function registerViews(): void
    {
        $viewsPath = $this->getModulePath().'/Views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->getModuleSlug());
        }
    }

    /**
     * Register module translations.
     */
    protected function registerTranslations(): void
    {
        $langPath = $this->getModulePath().'/Lang';

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->getModuleSlug());
        }
    }

    /**
     * Register module commands.
     */
    protected function registerCommands(): void
    {
        // Override in child classes to register module-specific commands
    }

    /**
     * Register module publishing.
     */
    protected function registerPublishing(): void
    {
        $moduleSlug = $this->getModuleSlug();
        $modulePath = $this->getModulePath();

        // Publish config
        $configPath = "{$modulePath}/Config/module.php";
        if (file_exists($configPath)) {
            $this->publishes([
                $configPath => config_path("modules/{$moduleSlug}.php"),
            ], "{$moduleSlug}-config");
        }

        // Publish views
        $viewsPath = "{$modulePath}/Views";
        if (is_dir($viewsPath)) {
            $this->publishes([
                $viewsPath => resource_path("views/vendor/{$moduleSlug}"),
            ], "{$moduleSlug}-views");
        }
    }
}
