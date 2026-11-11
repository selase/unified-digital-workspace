<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinContact;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/** Deletes only the rows a tenant gained since the demo seed began, and skips tables that do not exist. */
final class RowRemover
{
    /** Deletes the tenant's rows created at or after $since and returns how many went. */
    public function rows(string $table, Tenant $tenant, CarbonImmutable $since): int
    {
        if (! $this->has($table)) {
            return 0;
        }

        return $this->scoped($table, $tenant, $since)->delete();
    }

    /** Deletes child rows whose parent row falls inside the tenant's demo scope; call it before removing the parents. */
    public function children(string $child, string $foreignKey, string $parent, Tenant $tenant, CarbonImmutable $since): int
    {
        if (! $this->has($child) || ! $this->has($parent)) {
            return 0;
        }

        return $this->connection()->table($child)
            ->whereIn($foreignKey, $this->scoped($parent, $tenant, $since)->select('id'))
            ->delete();
    }

    private function scoped(string $table, Tenant $tenant, CarbonImmutable $since): \Illuminate\Database\Query\Builder
    {
        return $this->connection()->table($table)->where('tenant_id', $tenant->id)->where('created_at', '>=', $since);
    }

    private function has(string $table): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($table);
    }

    private function connection(): ConnectionInterface
    {
        return FinContact::query()->getConnection();
    }
}
