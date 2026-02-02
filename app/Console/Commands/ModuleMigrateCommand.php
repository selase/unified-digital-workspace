<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ModuleManager;
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
    public function handle(ModuleManager $moduleManager): int
    {
        $slug = $this->argument('slug');
        $modules = $slug
            ? collect([$moduleManager->find($slug)])->filter()
            : $moduleManager->all();

        if ($modules->isEmpty()) {
            $this->error($slug ? "Module '{$slug}' not found." : 'No modules found.');

            return self::FAILURE;
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
}
