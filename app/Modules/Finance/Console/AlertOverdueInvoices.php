<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Services\OverdueInvoiceAlerts;
use Illuminate\Console\Command;

final class AlertOverdueInvoices extends Command
{
    protected $signature = 'finance:alert-overdue';

    protected $description = 'Alert tenants about customer invoices that are past their due date.';

    public function handle(OverdueInvoiceAlerts $alerts): int
    {
        $this->info('Checked '.$alerts->run().' overdue invoice(s).');

        return self::SUCCESS;
    }
}
