<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeSiteCommand extends Command
{
    protected $signature = 'cms:make-site {tenant-slug : The tenant slug (e.g. acme-corp)}';

    protected $description = 'Scaffold a custom CMS site module for a specific tenant';

    public function __construct(
        private readonly Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantSlug = (string) $this->argument('tenant-slug');
        $studlyName = Str::studly($tenantSlug);
        $moduleName = "Site{$studlyName}";
        $moduleSlug = "site-{$tenantSlug}";
        $modulePath = app_path("Modules/{$moduleName}");

        if ($this->files->isDirectory($modulePath)) {
            $this->error("Module directory already exists: {$modulePath}");

            return self::FAILURE;
        }

        $this->info("Scaffolding site module: {$moduleName}");

        $directories = [
            'Config',
            'Database/Migrations',
            'Database/Factories',
            'Database/Seeders',
            'Http/Controllers/Web',
            'Http/Requests',
            'Jobs',
            'Models',
            'Providers',
            'Routes',
            'Services',
            'Views/public/layouts',
            'Views/components',
        ];

        foreach ($directories as $dir) {
            $this->files->makeDirectory("{$modulePath}/{$dir}", 0755, true);
        }

        // Generate module config
        $this->files->put(
            "{$modulePath}/Config/module.php",
            $this->generateConfig($moduleName, $moduleSlug, $tenantSlug)
        );

        // Generate service provider
        $this->files->put(
            "{$modulePath}/Providers/{$moduleName}ServiceProvider.php",
            $this->generateServiceProvider($moduleName, $moduleSlug, $tenantSlug)
        );

        // Generate public routes file
        $this->files->put(
            "{$modulePath}/Routes/public.php",
            $this->generatePublicRoutes()
        );

        // Generate empty web routes file
        $this->files->put(
            "{$modulePath}/Routes/web.php",
            $this->generateWebRoutes()
        );

        $this->info("Site module created at: app/Modules/{$moduleName}/");
        $this->info('');
        $this->info('Next steps:');
        $this->info("  1. Enable the module for the tenant: php artisan module:enable {$moduleSlug} --tenant=<tenant-uuid>");
        $this->info("  2. Add custom models in: app/Modules/{$moduleName}/Models/");
        $this->info("  3. Add migrations in: app/Modules/{$moduleName}/Database/Migrations/");
        $this->info("  4. Add controllers in: app/Modules/{$moduleName}/Http/Controllers/Web/");
        $this->info("  5. Define public routes in: app/Modules/{$moduleName}/Routes/public.php");
        $this->info("  6. Override views in: app/Modules/{$moduleName}/Views/public/");

        return self::SUCCESS;
    }

    private function generateConfig(string $moduleName, string $moduleSlug, string $tenantSlug): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        return [
            'name' => '{$moduleName}',
            'slug' => '{$moduleSlug}',
            'version' => '1.0.0',
            'description' => 'Custom CMS site for {$tenantSlug}',

            'namespace' => 'App\\\\Modules\\\\{$moduleName}',
            'provider' => 'App\\\\Modules\\\\{$moduleName}\\\\Providers\\\\{$moduleName}ServiceProvider',

            'tier' => 'custom',
            'is_billable' => false,

            'depends_on' => ['cms-core'],
            'conflicts_with' => [],

            'features' => [],
            'permissions' => [],

            'routes' => [
                'web' => true,
                'api' => false,
                'public' => true,
            ],

            'author' => 'UDW Team',
            'homepage' => null,
            'support' => null,
        ];
        PHP;
    }

    private function generateServiceProvider(string $moduleName, string $moduleSlug, string $tenantSlug): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Modules\\{$moduleName}\\Providers;

        use App\\Modules\\CmsCore\\Providers\\CmsSiteServiceProvider;

        final class {$moduleName}ServiceProvider extends CmsSiteServiceProvider
        {
            public function getModuleSlug(): string
            {
                return '{$moduleSlug}';
            }

            public function getModuleName(): string
            {
                return '{$moduleName}';
            }

            public function getTenantSlug(): string
            {
                return '{$tenantSlug}';
            }
        }
        PHP;
    }

    private function generatePublicRoutes(): string
    {
        return <<<'PHP'
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;

        // Define custom public routes here.
        // These routes are registered at both /site prefix (subdomain) and root (custom domain).
        // They take priority over the CMS Core catch-all /{slug} route.
        //
        // Example:
        // Route::get('/contact', [ContactController::class, 'show'])->name('contact');
        // Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
        PHP;
    }

    private function generateWebRoutes(): string
    {
        return <<<'PHP'
        <?php

        declare(strict_types=1);

        use Illuminate\Support\Facades\Route;

        // Define admin routes here (requires authentication).
        // These routes are registered at /{module-slug}/ prefix.
        PHP;
    }
}
