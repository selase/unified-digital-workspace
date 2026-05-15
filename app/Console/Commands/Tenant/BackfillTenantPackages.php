<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenant;

use App\Enum\TenantStatusEnum;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Assigns a package to every tenant that doesn't have one. Existing
 * assignments are left untouched — this is data-safe by design.
 *
 *   php artisan tenants:backfill-packages                        # assigns Free to unassigned
 *   php artisan tenants:backfill-packages --tenant=tgf --package=enterprise
 *   php artisan tenants:backfill-packages --dry-run
 *   php artisan tenants:backfill-packages --force                # overwrite existing
 */
final class BackfillTenantPackages extends Command
{
    /** @var string */
    protected $signature = 'tenants:backfill-packages
        {--tenant= : Limit to a single tenant slug or UUID}
        {--package=free : Package slug to assign}
        {--dry-run : Print intended changes without writing}
        {--force : Overwrite an existing package_id (use with care)}';

    /** @var string */
    protected $description = 'Assign a package to tenants that have none (or with --force, replace assignments)';

    public function handle(): int
    {
        $packageSlug = (string) $this->option('package');
        $package = Package::query()->where('slug', $packageSlug)->first();

        if (! $package) {
            $this->error("Package not found: {$packageSlug}");

            return self::FAILURE;
        }

        $query = Tenant::query()->where('status', '!=', TenantStatusEnum::BANNED);

        if ($identifier = (string) $this->option('tenant')) {
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier) === 1;
            $query->where($isUuid ? 'id' : 'slug', $identifier);
        }

        if (! $this->option('force')) {
            $query->whereNull('package_id');
        }

        $tenants = $query->get();
        $dryRun = (bool) $this->option('dry-run');

        if ($tenants->isEmpty()) {
            $this->info('No tenants match the criteria — nothing to do.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s "%s" (%s) to %d tenant(s)',
            $dryRun ? '[dry-run] Would assign' : 'Assigning',
            $package->name,
            $package->slug,
            $tenants->count()
        ));

        foreach ($tenants as $tenant) {
            $current = $tenant->package_id;
            $note = $current === null
                ? 'unassigned'
                : ($current === $package->id ? 'already on '.$package->slug : 'replacing previous');

            if ($dryRun) {
                $this->line("  [dry-run] {$tenant->slug} ({$note})");

                continue;
            }

            $tenant->update(['package_id' => $package->id]);
            $this->line("  {$tenant->slug} ({$note})");
        }

        return self::SUCCESS;
    }
}
