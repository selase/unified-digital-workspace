<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ModuleManager;
use Illuminate\Console\Command;

final class ModuleListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:list
                            {--tenant= : Show enabled status for a specific tenant (UUID)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all available modules';

    /**
     * Execute the console command.
     */
    public function handle(ModuleManager $moduleManager): int
    {
        $modules = $moduleManager->all();

        if ($modules->isEmpty()) {
            $this->info('No modules found in app/Modules/');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        if ($tenantId && ! $tenant) {
            $this->error("Tenant with ID '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $headers = ['Slug', 'Name', 'Version', 'Tier', 'Dependencies'];
        if ($tenant) {
            $headers[] = 'Enabled';
        }

        $rows = $modules->map(function ($module) use ($moduleManager, $tenant) {
            $row = [
                $module['slug'],
                $module['name'],
                $module['version'] ?? '1.0.0',
                $module['tier'] ?? 'free',
                implode(', ', $module['depends_on'] ?? []) ?: '-',
            ];

            if ($tenant) {
                $isEnabled = $moduleManager->isEnabledForTenant($module['slug'], $tenant);
                $row[] = $isEnabled ? '<fg=green>Yes</>' : '<fg=red>No</>';
            }

            return $row;
        })->toArray();

        $this->table($headers, $rows);

        if ($tenant) {
            $this->info("Showing status for tenant: {$tenant->name} ({$tenant->id})");
        }

        return self::SUCCESS;
    }
}
