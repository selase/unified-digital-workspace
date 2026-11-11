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
