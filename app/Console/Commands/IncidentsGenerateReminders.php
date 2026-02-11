<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\IncidentManagement\Services\IncidentReminderService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;

final class IncidentsGenerateReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'incidents:generate-reminders {--tenant= : Tenant ID to process}';

    /**
     * @var string
     */
    protected $description = 'Generate upcoming incident reminder records.';

    public function handle(
        TenantContext $context,
        TenantDatabaseManager $dbManager,
        ModuleManager $moduleManager,
        IncidentReminderService $service
    ): int {
        $tenantId = (string) $this->option('tenant');

        $query = Tenant::where('status', 'active');
        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = $query->get();

        $count = 0;

        foreach ($tenants as $tenant) {
            $context->setTenant($tenant);

            if ($tenant->requiresDedicatedDb()) {
                $dbManager->configure($tenant);
            } else {
                $dbManager->configureShared();
            }

            if (! $moduleManager->isEnabledForTenant('incident-management', $tenant)) {
                continue;
            }

            $count += $service->generateUpcomingReminders();
        }

        $this->info("Incident reminders generated: {$count}");

        return self::SUCCESS;
    }
}
