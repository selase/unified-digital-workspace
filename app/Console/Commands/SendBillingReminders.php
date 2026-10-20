<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\BillingCommunicationService;
use Illuminate\Console\Command;

final class SendBillingReminders extends Command
{
    protected $signature = 'billing:send-reminders';

    protected $description = 'Queue upcoming-cycle and unpaid-invoice messages once per billing contact and milestone';

    public function handle(BillingCommunicationService $communications): int
    {
        $count = $communications->runDue();
        $this->info("{$count} new billing messages recorded; pending transport deliveries queued.");

        return self::SUCCESS;
    }
}
