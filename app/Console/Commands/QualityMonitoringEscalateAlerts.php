<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Notifications\QualityMonitoring\QualityAlertEscalationNotification;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class QualityMonitoringEscalateAlerts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'quality:escalate-alerts {--tenant= : Tenant ID to process}';

    /**
     * @var string
     */
    protected $description = 'Escalate open Quality Monitoring alerts to workplan owners';

    public function handle(TenantContext $context, TenantDatabaseManager $dbManager, ModuleManager $moduleManager): int
    {
        $tenantId = (string) $this->option('tenant');

        $query = Tenant::where('status', 'active');
        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = $query->get();
        $thresholdDays = (int) config('modules.quality-monitoring.alert_escalation_days', 3);
        $escalated = 0;

        foreach ($tenants as $tenant) {
            $context->setTenant($tenant);

            if ($tenant->requiresDedicatedDb()) {
                $dbManager->configure($tenant);
            } else {
                $dbManager->configureShared();
            }

            if (! $moduleManager->isEnabledForTenant('quality-monitoring', $tenant)) {
                continue;
            }

            $escalated += $this->escalateTenantAlerts($thresholdDays);
        }

        $this->info("Quality monitoring alerts escalated: {$escalated}");

        return Command::SUCCESS;
    }

    private function escalateTenantAlerts(int $thresholdDays): int
    {
        $alerts = Alert::query()
            ->where('status', 'open')
            ->where('escalation_level', '<', 1)
            ->where('created_at', '<=', now()->subDays($thresholdDays))
            ->with('workplan')
            ->get();

        $escalated = 0;

        foreach ($alerts as $alert) {
            $workplan = $alert->workplan;
            $ownerId = $workplan?->owner_id;

            if (! $ownerId) {
                continue;
            }

            $user = User::query()->find($ownerId);
            if (! $user) {
                continue;
            }

            $connection = $user->getConnectionName() ?? config('database.default');
            if (! $connection || ! Schema::connection($connection)->hasTable('notifications')) {
                continue;
            }

            $user->notify(new QualityAlertEscalationNotification($alert, 1));
            $alert->escalation_level = 1;
            $alert->escalated_at = now();
            $alert->save();
            $escalated++;
        }

        return $escalated;
    }
}
