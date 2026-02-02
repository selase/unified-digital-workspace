<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ModuleManager;
use Illuminate\Support\ServiceProvider;

final class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register ModuleManager as a singleton
        $this->app->singleton(ModuleManager::class, function ($app) {
            return new ModuleManager();
        });

        // Register alias for easier access
        $this->app->alias(ModuleManager::class, 'module-manager');

        // Discover and register all module service providers
        $this->registerModuleProviders();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Module providers will boot themselves
    }

    /**
     * Discover and register module service providers.
     */
    protected function registerModuleProviders(): void
    {
        $moduleManager = $this->app->make(ModuleManager::class);
        $modules = $moduleManager->discoverModules();

        foreach ($modules as $module) {
            if (isset($module['provider']) && class_exists($module['provider'])) {
                $this->app->register($module['provider']);
            }
        }
    }
}
