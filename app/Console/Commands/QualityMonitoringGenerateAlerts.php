<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Notifications\QualityMonitoring\QualityAlertNotification;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class QualityMonitoringGenerateAlerts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'quality:generate-alerts {--tenant= : Tenant ID to process}';

    /**
     * @var string
     */
    protected $description = 'Generate Quality Monitoring alerts for overdue activities and KPIs';

    public function handle(TenantContext $context, TenantDatabaseManager $dbManager, ModuleManager $moduleManager): int
    {
        $tenantId = (string) $this->option('tenant');

        $query = Tenant::where('status', 'active');
        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = $query->get();

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

            $this->generateActivityAlerts($tenant);
            $this->generateKpiAlerts($tenant);
        }

        $this->info('Quality monitoring alerts generated.');

        return Command::SUCCESS;
    }

    private function generateActivityAlerts(Tenant $tenant): void
    {
        $overdueActivities = Activity::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->where('status', '!=', 'done')
            ->get();

        foreach ($overdueActivities as $activity) {
            $exists = Alert::query()
                ->where('type', 'activity_overdue')
                ->where('workplan_id', $activity->objective?->workplan_id)
                ->where('metadata->activity_id', $activity->id)
                ->where('status', 'open')
                ->exists();

            if ($exists) {
                continue;
            }

            $alert = Alert::create([
                'workplan_id' => $activity->objective?->workplan_id,
                'type' => 'activity_overdue',
                'status' => 'open',
                'metadata' => [
                    'activity_id' => $activity->id,
                    'activity_title' => $activity->title,
                    'due_date' => $activity->due_date?->toDateString(),
                ],
            ]);

            if ($activity->responsible_id) {
                $user = User::query()->find($activity->responsible_id);
                $this->sendAlertNotification($user, $alert);
            }
        }
    }

    private function generateKpiAlerts(Tenant $tenant): void
    {
        $kpis = Kpi::query()->get();

        foreach ($kpis as $kpi) {
            $threshold = $this->frequencyThreshold($kpi->frequency);
            if ($threshold === null) {
                continue;
            }

            $lastUpdate = $kpi->updates()->latest('captured_at')->first();
            $lastDate = $lastUpdate?->captured_at ?? $lastUpdate?->created_at;

            if (! $lastDate || $lastDate->diffInDays(now()) < $threshold) {
                continue;
            }

            $exists = Alert::query()
                ->where('type', 'kpi_overdue')
                ->where('kpi_id', $kpi->id)
                ->where('status', 'open')
                ->exists();

            if ($exists) {
                continue;
            }

            $alert = Alert::create([
                'workplan_id' => $kpi->activity?->objective?->workplan_id,
                'kpi_id' => $kpi->id,
                'type' => 'kpi_overdue',
                'status' => 'open',
                'metadata' => [
                    'kpi_name' => $kpi->name,
                    'frequency' => $kpi->frequency,
                    'last_update' => $lastDate?->toDateString(),
                ],
            ]);

            $userId = $kpi->activity?->responsible_id;
            if ($userId) {
                $user = User::query()->find($userId);
                $this->sendAlertNotification($user, $alert);
            }
        }
    }

    private function frequencyThreshold(?string $frequency): ?int
    {
        return match ($frequency) {
            'monthly' => 30,
            'quarterly' => 90,
            'mid-year' => 180,
            'annual' => 365,
            default => null,
        };
    }

    private function sendAlertNotification(?User $user, Alert $alert): void
    {
        if (! $user) {
            return;
        }

        $connection = $user->getConnectionName() ?? config('database.default');
        if (! $connection || ! Schema::connection($connection)->hasTable('notifications')) {
            return;
        }

        $user->notify(new QualityAlertNotification($alert));
        $alert->sent_at = now();
        $alert->save();
    }
}
