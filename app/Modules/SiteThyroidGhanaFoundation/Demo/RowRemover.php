<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinContact;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Deletes only rows the demo seed created: parents by their "Demo: " label, children through a demo parent.
 * A table with no label (finance) is handled by time alone, behind FinanceGuard. Missing tables are skipped.
 */
final class RowRemover
{
    /** Deletes the tenant's rows created at or after $since, optionally only those whose label column starts with "Demo: ". */
    public function rows(string $table, Tenant $tenant, CarbonImmutable $since, ?string $labelColumn = null): int
    {
        return $this->has($table) ? $this->scoped($table, $tenant, $since, $labelColumn)->delete() : 0;
    }

    /** Deletes child rows whose parent is in the demo scope; call it before removing the parents. */
    public function children(string $child, string $foreignKey, string $parent, Tenant $tenant, CarbonImmutable $since, ?string $parentLabel = null): int
    {
        if (! $this->has($child) || ! $this->has($parent)) {
            return 0;
        }

        return $this->connection()->table($child)->whereIn($foreignKey, $this->scoped($parent, $tenant, $since, $parentLabel)->select('id'))->delete();
    }

    /** Ids of rows in $table that belong to the given parent ids, for removing grandchildren. */
    public function idsBelongingTo(string $table, string $foreignKey, Builder $parentIds): Builder
    {
        return $this->connection()->table($table)->whereIn($foreignKey, $parentIds)->select('id');
    }

    /** The ids of the tenant's demo parents. */
    public function demoIds(string $table, Tenant $tenant, CarbonImmutable $since, string $labelColumn): Builder
    {
        return $this->scoped($table, $tenant, $since, $labelColumn)->select('id');
    }

    public function deleteWhereIn(string $table, string $column, Builder $ids): int
    {
        return $this->has($table) ? $this->connection()->table($table)->whereIn($column, $ids)->delete() : 0;
    }

    private function scoped(string $table, Tenant $tenant, CarbonImmutable $since, ?string $labelColumn): Builder
    {
        $query = $this->connection()->table($table)->where('tenant_id', $tenant->id)->where('created_at', '>=', $since);

        return $labelColumn === null ? $query : $query->where($labelColumn, 'like', PublicVisibility::PREFIX.'%');
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
