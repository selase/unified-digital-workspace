<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantDatabaseRemover;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class TenantsDelete extends Command
{
    protected $signature = 'tenants:delete
        {tenant : Tenant slug or UUID}
        {--no-backup : Drop a dedicated tenant database without backing it up first}
        {--force : Do not ask for confirmation}';

    protected $description = 'Delete a tenant and, for db_per_tenant tenants, its database (backed up first unless --no-backup)';

    public function handle(TenantDatabaseRemover $remover): int
    {
        $identifier = (string) $this->argument('tenant');

        $tenant = Tenant::query()
            ->when(
                Str::isUuid($identifier),
                fn ($query) => $query->where('id', $identifier),
                fn ($query) => $query->where('slug', $identifier)
            )
            ->first();

        if (! $tenant instanceof Tenant) {
            $this->error("Tenant '{$identifier}' not found.");

            return self::FAILURE;
        }

        $backup = ! $this->option('no-backup');
        $mode = $tenant->requiresDedicatedDb()
            ? ($backup ? 'its database will be backed up, then dropped' : 'its database will be dropped WITHOUT a backup')
            : 'it uses the shared database, so only the tenant record is deleted';

        if (! $this->option('force') && ! $this->confirm("Delete tenant '{$tenant->slug}'? ({$mode})")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $remover->remove($tenant, $backup);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tenant->delete();

        $this->info("Tenant '{$tenant->slug}' deleted.");

        if ($result['backup'] !== null) {
            $this->info('Database backup: '.$result['backup']);
        }

        return self::SUCCESS;
    }
}
