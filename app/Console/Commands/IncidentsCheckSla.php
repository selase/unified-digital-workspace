<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Incidents\IncidentSlaBreached;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\IncidentManagement\Models\IncidentSla;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

final class IncidentsCheckSla extends Command
{
    /**
     * @var string
     */
    protected $signature = 'incidents:check-sla {--tenant= : Tenant ID to process}';

    /**
     * @var string
     */
    protected $description = 'Check incident SLA deadlines and mark breaches.';

    public function handle(TenantContext $context, TenantDatabaseManager $dbManager, ModuleManager $moduleManager): int
    {
        $now = now();

        $breachedCount = 0;

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

            if (! $moduleManager->isEnabledForTenant('incident-management', $tenant)) {
                continue;
            }

            $breachingSlas = IncidentSla::query()
                ->where('is_breached', false)
                ->whereNotNull('resolution_due_at')
                ->whereNull('resolution_at')
                ->where('resolution_due_at', '<', $now)
                ->with('incident')
                ->get();

            foreach ($breachingSlas as $sla) {
                $sla->fill([
                    'is_breached' => true,
                    'breached_at' => $now,
                ]);

                $sla->save();
                $breachedCount++;

                $incident = $sla->incident;

                if ($incident && $incident->assigned_to_id) {
                    $assignee = User::query()->find($incident->assigned_to_id);

                    if ($assignee && $assignee->email) {
                        Mail::to($assignee->email)->queue(new IncidentSlaBreached($incident));
                    }
                }
            }
        }

        $this->info("Incident SLA breaches marked: {$breachedCount}");

        return self::SUCCESS;
    }
}
