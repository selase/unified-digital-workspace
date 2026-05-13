<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Enum\TenantStatusEnum;
use App\Models\Tenant;
use App\Services\ModuleManager;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-shot recovery for tenants that existed before the auto-onboarding
 * observer was wired up. Walks every non-banned tenant and enables any
 * fundamental modules they're missing.
 *
 *   php artisan tenant:backfill-onboarding
 *   php artisan tenant:backfill-onboarding --tenant=thyroid-ghana-foundation
 *   php artisan tenant:backfill-onboarding --dry-run
 */
final class BackfillTenantOnboarding extends Command
{
    /** @var string */
    protected $signature = 'tenant:backfill-onboarding
        {--tenant= : Limit to a single tenant slug or UUID}
        {--dry-run : Print what would happen without changing state}';

    /** @var string */
    protected $description = 'Enable fundamental modules on tenants that missed them';

    public function handle(ModuleManager $moduleManager): int
    {
        $fundamental = (array) config('modules.fundamental', []);

        if ($fundamental === []) {
            $this->warn('No fundamental modules configured (config/modules.php). Nothing to do.');

            return self::SUCCESS;
        }

        $query = Tenant::query()->where('status', '!=', TenantStatusEnum::BANNED);

        if ($identifier = (string) $this->option('tenant')) {
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier) === 1;
            $query->where($isUuid ? 'id' : 'slug', $identifier);
        }

        $tenants = $query->get();
        $dryRun = (bool) $this->option('dry-run');

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d tenant(s) against fundamental modules: %s',
            $dryRun ? '[dry-run] Would scan' : 'Scanning',
            $tenants->count(),
            implode(', ', $fundamental)
        ));

        $enabledTotal = 0;

        foreach ($tenants as $tenant) {
            foreach ($fundamental as $slug) {
                if (! $moduleManager->exists($slug)) {
                    continue;
                }

                if ($moduleManager->isEnabledForTenant($slug, $tenant)) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [dry-run] would enable '{$slug}' on {$tenant->slug}");
                    $enabledTotal++;

                    continue;
                }

                try {
                    $moduleManager->enableForTenant($slug, $tenant);
                    $this->line("  enabled '{$slug}' on {$tenant->slug}");
                    $enabledTotal++;
                } catch (Throwable $e) {
                    $this->error("  failed '{$slug}' on {$tenant->slug}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d module(s) %senabled.',
            $dryRun ? '[dry-run]' : 'Done.',
            $enabledTotal,
            $dryRun ? '' : ''
        ));

        return self::SUCCESS;
    }
}
