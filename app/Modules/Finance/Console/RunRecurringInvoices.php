<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

final class RunRecurringInvoices extends Command
{
    protected $signature = 'finance:run-recurring';

    protected $description = 'Create invoices from recurring templates that are due today.';

    public function handle(RecurringInvoiceService $service): int
    {
        $this->info('Created '.$service->runDue().' recurring invoice(s).');

        return self::SUCCESS;
    }
}
