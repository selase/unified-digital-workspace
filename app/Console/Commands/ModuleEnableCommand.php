<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ModuleConflictException;
use App\Exceptions\ModuleDependencyException;
use App\Exceptions\ModuleNotFoundException;
use App\Models\Tenant;
use App\Services\ModuleManager;
use Illuminate\Console\Command;

final class ModuleEnableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:enable
                            {slug : The module slug to enable}
                            {--tenant= : The tenant UUID to enable the module for}
                            {--all-tenants : Enable for all tenants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable a module for a tenant';

    /**
     * Execute the console command.
     */
    public function handle(ModuleManager $moduleManager): int
    {
        $slug = $this->argument('slug');
        $tenantId = $this->option('tenant');
        $allTenants = $this->option('all-tenants');

        if (! $tenantId && ! $allTenants) {
            $this->error('You must specify either --tenant=<UUID> or --all-tenants');

            return self::FAILURE;
        }

        if (! $moduleManager->exists($slug)) {
            $this->error("Module '{$slug}' not found.");

            return self::FAILURE;
        }

        $tenants = $allTenants
            ? Tenant::all()
            : collect([Tenant::find($tenantId)]);

        if ($tenants->isEmpty() || $tenants->first() === null) {
            $this->error($allTenants ? 'No tenants found.' : "Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            try {
                $moduleManager->enableForTenant($slug, $tenant);
                $this->info("Module '{$slug}' enabled for tenant: {$tenant->name}");
                $successCount++;
            } catch (ModuleNotFoundException $e) {
                $this->error("Module not found: {$e->getMessage()}");
                $failCount++;
            } catch (ModuleDependencyException $e) {
                $this->error("Dependency error for {$tenant->name}: {$e->getMessage()}");
                $failCount++;
            } catch (ModuleConflictException $e) {
                $this->error("Conflict error for {$tenant->name}: {$e->getMessage()}");
                $failCount++;
            }
        }

        if ($allTenants) {
            $this->info("Enabled for {$successCount} tenant(s), failed for {$failCount} tenant(s).");
        }

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
