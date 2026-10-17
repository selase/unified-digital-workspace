<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Console\Commands;

use App\Modules\HrmsCore\Services\LeaveAccrualService;
use Illuminate\Console\Command;

final class HrmsLeaveRolloverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hrms:leave-rollover
                            {fromYear? : The closing year to rollover from (defaults to previous year)}
                            {toYear? : The target year to rollover into (defaults to current year)}
                            {--tenant= : Optional specific tenant ID}
                            {--max-carry=10 : Maximum carry-forward days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollover employee leave balances to a new calendar year with carry-forward rules';

    public function handle(LeaveAccrualService $service): int
    {
        $currentYear = (int) date('Y');
        $fromYear = $this->argument('fromYear') !== null
            ? (int) $this->argument('fromYear')
            : $currentYear - 1;
        $toYear = $this->argument('toYear') !== null
            ? (int) $this->argument('toYear')
            : $currentYear;

        $tenantId = $this->option('tenant') !== null ? (string) $this->option('tenant') : null;
        $maxCarry = (int) $this->option('max-carry');

        $this->info("Rolling over leave balances from {$fromYear} to {$toYear} (max carry-forward: {$maxCarry} days)...");

        $count = $service->rolloverYear($fromYear, $toYear, $tenantId, $maxCarry);

        $this->info("Successfully processed and updated {$count} leave balance record(s).");

        return self::SUCCESS;
    }
}
