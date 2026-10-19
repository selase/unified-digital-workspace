<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Console\Commands;

use App\Models\Tenant;
use App\Modules\HrmsCore\Services\LeaveAccrualService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
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

    public function handle(
        LeaveAccrualService $service,
        TenantContext $context,
        TenantDatabaseManager $dbManager,
        ModuleManager $moduleManager
    ): int {
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

        $tenants = Tenant::query()
            ->where('status', 'active')
            ->when($tenantId !== null, fn ($query) => $query->whereKey($tenantId))
            ->get();

        $count = 0;

        foreach ($tenants as $tenant) {
            $context->setTenant($tenant);

            if ($tenant->requiresDedicatedDb()) {
                $dbManager->configure($tenant);
            } else {
                $dbManager->configureShared();
            }

            if (! $moduleManager->isEnabledForTenant('hrms-core', $tenant)) {
                continue;
            }

            $count += $service->rolloverYear($fromYear, $toYear, (string) $tenant->id, $maxCarry);
        }

        $this->info("Successfully processed and updated {$count} leave balance record(s).");

        return self::SUCCESS;
    }
}
