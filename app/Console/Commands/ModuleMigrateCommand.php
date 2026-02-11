<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class ModuleMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:migrate
                            {slug? : The module slug to migrate (optional, migrates all if not provided)}
                            {--tenant= : The tenant UUID to migrate against}
                            {--all-tenants : Run module migrations for all tenants}
                            {--rollback : Rollback migrations}
                            {--fresh : Drop all tables and re-run migrations}
                            {--seed : Run seeders after migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for a module or all modules';

    /**
     * Execute the console command.
     */
    public function handle(ModuleManager $moduleManager, TenantDatabaseManager $dbManager): int
    {
        $slug = $this->argument('slug');
        $tenantId = (string) $this->option('tenant');
        $allTenants = (bool) $this->option('all-tenants');

        if ($tenantId && $allTenants) {
            $this->error('Use either --tenant or --all-tenants, not both.');

            return self::FAILURE;
        }

        $modules = $slug
            ? collect([$moduleManager->find($slug)])->filter()
            : $moduleManager->all();

        if ($modules->isEmpty()) {
            $this->error($slug ? "Module '{$slug}' not found." : 'No modules found.');

            return self::FAILURE;
        }

        if ($tenantId || $allTenants) {
            return $this->runForTenants($modules, $dbManager, $tenantId, $allTenants);
        }

        foreach ($modules as $module) {
            $migrationPath = $module['path'].'/Database/Migrations';

            if (! is_dir($migrationPath)) {
                $this->warn("No migrations found for module: {$module['slug']}");

                continue;
            }

            $this->info("Running migrations for module: {$module['slug']}");

            $relativePath = str_replace(base_path().'/', '', $migrationPath);

            if ($this->option('fresh')) {
                $this->warn("Fresh migration requested. This will drop all tables for module: {$module['slug']}");

                Artisan::call('migrate:fresh', [
                    '--path' => $relativePath,
                    '--force' => true,
                ], $this->output);
            } elseif ($this->option('rollback')) {
                Artisan::call('migrate:rollback', [
                    '--path' => $relativePath,
                    '--force' => true,
                ], $this->output);
            } else {
                Artisan::call('migrate', [
                    '--path' => $relativePath,
                    '--force' => true,
                ], $this->output);
            }

            if ($this->option('seed')) {
                $seederClass = $module['namespace'].'\\Database\\Seeders\\DatabaseSeeder';

                if (class_exists($seederClass)) {
                    $this->info("Running seeder for module: {$module['slug']}");
                    Artisan::call('db:seed', [
                        '--class' => $seederClass,
                        '--force' => true,
                    ], $this->output);
                }
            }
        }

        $this->info('Module migrations completed.');

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $modules
     */
    private function runForTenants(\Illuminate\Support\Collection $modules, TenantDatabaseManager $dbManager, string $tenantId, bool $allTenants): int
    {
        $tenants = $allTenants
            ? Tenant::where('status', 'active')->get()
            : collect([Tenant::find($tenantId)]);

        if ($tenants->isEmpty() || $tenants->first() === null) {
            $this->error($allTenants ? 'No tenants found.' : "Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $failed = false;

        foreach ($tenants as $tenant) {
            if ($tenant->requiresDedicatedDb()) {
                $dbManager->configure($tenant);
            } else {
                $dbManager->configureShared();
            }

            foreach ($modules as $module) {
                $migrationPath = $module['path'].'/Database/Migrations';

                if (! is_dir($migrationPath)) {
                    $this->warn("No migrations found for module: {$module['slug']}");

                    continue;
                }

                $this->info("Running migrations for module: {$module['slug']} on tenant {$tenant->name}");

                $relativePath = str_replace(base_path().'/', '', $migrationPath);

                if ($this->option('fresh')) {
                    $this->warn("Fresh migration requested. This will drop all tables for module: {$module['slug']} on tenant {$tenant->name}");

                    $exitCode = Artisan::call('migrate:fresh', [
                        '--database' => 'tenant',
                        '--path' => $relativePath,
                        '--force' => true,
                    ], $this->output);
                    if ($exitCode !== 0) {
                        $failed = true;
                    }
                } elseif ($this->option('rollback')) {
                    $exitCode = Artisan::call('migrate:rollback', [
                        '--database' => 'tenant',
                        '--path' => $relativePath,
                        '--force' => true,
                    ], $this->output);
                    if ($exitCode !== 0) {
                        $failed = true;
                    }
                } else {
                    $exitCode = Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => $relativePath,
                        '--force' => true,
                    ], $this->output);
                    if ($exitCode !== 0) {
                        $failed = true;
                    }
                }

                if ($this->option('seed')) {
                    $seederClass = $module['namespace'].'\\Database\\Seeders\\DatabaseSeeder';

                    if (class_exists($seederClass)) {
                        $this->info("Running seeder for module: {$module['slug']} on tenant {$tenant->name}");
                        Artisan::call('db:seed', [
                            '--class' => $seederClass,
                            '--database' => 'tenant',
                            '--force' => true,
                        ], $this->output);
                    }
                }
            }
        }

        $this->info('Module migrations completed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
