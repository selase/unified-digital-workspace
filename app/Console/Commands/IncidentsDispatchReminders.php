<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Incidents\IncidentReminder as IncidentReminderMail;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentReminder;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

final class IncidentsDispatchReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'incidents:dispatch-reminders {--tenant= : Tenant ID to process}';

    /**
     * @var string
     */
    protected $description = 'Dispatch scheduled incident reminders.';

    public function handle(TenantContext $context, TenantDatabaseManager $dbManager, ModuleManager $moduleManager): int
    {
        $now = now();

        $tenantId = (string) $this->option('tenant');

        $query = Tenant::where('status', 'active');
        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = $query->get();

        $dispatchedCount = 0;

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

            $reminders = IncidentReminder::query()
                ->whereNull('sent_at')
                ->where('scheduled_for', '<=', $now)
                ->get();

            foreach ($reminders as $reminder) {
                $incident = Incident::query()->find($reminder->incident_id);

                if ($incident) {
                    $userId = Arr::get($reminder->metadata ?? [], 'user_id') ?: $incident->assigned_to_id;

                    if ($userId) {
                        $user = User::query()
                            ->where('uuid', $userId)
                            ->first();

                        if ($user && $user->email) {
                            Mail::to($user->email)->queue(new IncidentReminderMail($incident, $reminder));
                        }
                    }
                }

                $reminder->sent_at = $now;
                $reminder->save();
                $dispatchedCount++;
            }
        }

        $this->info("Incident reminders dispatched: {$dispatchedCount}");

        return self::SUCCESS;
    }
}
