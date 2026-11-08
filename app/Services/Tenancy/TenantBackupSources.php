<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enum\TenantStatusEnum;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Adds every active dedicated-database tenant to the nightly backup, so a tenant
 * with its own database is protected like the shared one.
 */
final class TenantBackupSources
{
    public function __construct(private readonly TenantDatabaseManager $databases) {}

    /** @return Collection<int, Tenant> */
    public function dedicatedTenants(): Collection
    {
        return Tenant::query()
            ->where('status', TenantStatusEnum::ACTIVE)
            ->whereIn('isolation_mode', ['db_per_tenant', 'byo'])
            ->get();
    }

    /**
     * Tenants whose database cannot be located, so a backup would silently skip them.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->dedicatedTenants()
            ->filter(fn (Tenant $tenant): bool => empty($tenant->db_secret_ref) && empty($tenant->meta['database']))
            ->map(fn (Tenant $tenant): string => $tenant->name.' ('.$tenant->id.') has no database name or secret to back up')
            ->values()
            ->all();
    }

    /**
     * Defines a connection per dedicated tenant and lists it as a backup source.
     *
     * @return list<string> the connection names added
     */
    public function register(): array
    {
        $added = [];

        foreach ($this->dedicatedTenants() as $tenant) {
            if (empty($tenant->db_secret_ref) && empty($tenant->meta['database'])) {
                continue;
            }

            $connection = 'backup_tenant_'.str_replace('-', '_', (string) $tenant->id);
            Config::set('database.connections.'.$connection, $this->databases->connectionConfig($tenant));
            $added[] = $connection;
        }

        $sources = array_values(array_unique([...(array) Config::get('backup.backup.source.databases', []), ...$added]));
        Config::set('backup.backup.source.databases', $sources);

        return $added;
    }
}
