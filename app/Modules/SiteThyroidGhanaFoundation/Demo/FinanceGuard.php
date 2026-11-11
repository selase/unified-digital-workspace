<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo;

use App\Models\Tenant;
use App\Modules\Finance\Models\FinContact;
use Carbon\CarbonImmutable;

/** Finance rows cannot be told apart from demo rows by name, so removal only proceeds when none predate the seed. */
final class FinanceGuard
{
    private const TABLES = ['fin_contacts', 'fin_journals', 'fin_invoices', 'fin_payments', 'fin_bills', 'fin_expenses'];

    /** @return array<string, int> */
    public function signature(Tenant $tenant): array
    {
        $connection = FinContact::query()->getConnection();
        $counts = [];

        foreach (self::TABLES as $table) {
            if ($connection->getSchemaBuilder()->hasTable($table)) {
                $counts[$table] = $connection->table($table)->where('tenant_id', $tenant->id)->count();
            }
        }

        return $counts;
    }

    /** @param  array<string, int>  $seeded  The finance row counts recorded when seeding finished; empty when it never finished. */
    public function assertUntouched(Tenant $tenant, CarbonImmutable $since, array $seeded): void
    {
        $this->assertNoRealRows($tenant, $since);

        $current = $this->signature($tenant);
        ksort($current);
        ksort($seeded);

        if ($seeded !== [] && $current !== $seeded) {
            throw new DemoRemovalBlocked('Finance records changed after the demo was seeded, so they cannot be told apart from demo data. Nothing was removed.');
        }
    }

    public function assertNoRealRows(Tenant $tenant, CarbonImmutable $since): void
    {
        $connection = FinContact::query()->getConnection();
        $blocking = [];

        foreach (self::TABLES as $table) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            if ($connection->table($table)->where('tenant_id', $tenant->id)->where('created_at', '<', $since)->exists()) {
                $blocking[] = $table;
            }
        }

        if ($blocking !== []) {
            throw new DemoRemovalBlocked('Real finance data predates the demo seed ('.implode(', ', $blocking).'). Nothing was removed.');
        }
    }
}
