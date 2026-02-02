<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ModuleDependencyException;
use App\Exceptions\ModuleNotFoundException;
use App\Models\Tenant;
use App\Services\ModuleManager;
use Illuminate\Console\Command;

final class ModuleDisableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:disable
                            {slug : The module slug to disable}
                            {--tenant= : The tenant UUID to disable the module for}
                            {--all-tenants : Disable for all tenants}
                            {--force : Force disable even if other modules depend on it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable a module for a tenant';

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

        if ($slug === 'core') {
            $this->error("The 'core' module cannot be disabled.");

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
                $moduleManager->disableForTenant($slug, $tenant);
                $this->info("Module '{$slug}' disabled for tenant: {$tenant->name}");
                $successCount++;
            } catch (ModuleNotFoundException $e) {
                $this->error("Module not found: {$e->getMessage()}");
                $failCount++;
            } catch (ModuleDependencyException $e) {
                $this->error("Dependency error for {$tenant->name}: {$e->getMessage()}");
                $failCount++;
            }
        }

        if ($allTenants) {
            $this->info("Disabled for {$successCount} tenant(s), failed for {$failCount} tenant(s).");
        }

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
