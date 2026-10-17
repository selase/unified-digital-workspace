<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Console\Commands;

use App\Models\User;
use App\Modules\FacilitiesManagement\Models\FacilityWorkOrder;
use App\Modules\FacilitiesManagement\Models\PreventiveMaintenanceSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class GeneratePreventiveWorkOrdersCommand extends Command
{
    protected $signature = 'facilities:generate-preventive-work-orders {--tenant= : Scope generation to a specific tenant ID} {--force : Force generation even if due date is in the future}';

    protected $description = 'Generate preventive maintenance work orders for due schedules and advance schedule dates';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $force = (bool) $this->option('force');

        $query = PreventiveMaintenanceSchedule::query()
            ->with(['asset.building'])
            ->where('is_active', true);

        if (! empty($tenantId)) {
            $query->where('tenant_id', (string) $tenantId);
        }

        if (! $force) {
            $query->where('next_due_date', '<=', now()->toDateString());
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('No preventive maintenance schedules due for generation.');

            return self::SUCCESS;
        }

        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($schedules as $schedule) {
            $targetDueDate = $schedule->next_due_date ? $schedule->next_due_date->toDateString() : now()->toDateString();

            $alreadyExists = FacilityWorkOrder::query()
                ->where('preventive_maintenance_schedule_id', $schedule->id)
                ->where('scheduled_due_date', $targetDueDate)
                ->exists();

            if ($alreadyExists) {
                $skippedCount++;

                continue;
            }

            $building = $schedule->asset ? $schedule->asset->building : null;
            $requesterId = $building ? $building->manager_id : null;
            if (! $requesterId) {
                $requesterId = User::query()->where('tenant_id', $schedule->tenant_id)->value('uuid')
                    ?? User::query()->value('uuid');
            }

            $assetCategory = $schedule->asset ? $schedule->asset->category : 'general_maintenance';
            $assetCriticality = $schedule->asset ? $schedule->asset->criticality : 'medium';
            $assetName = $schedule->asset ? $schedule->asset->name : 'Asset';
            $assetTag = $schedule->asset ? $schedule->asset->asset_tag : 'N/A';

            $priority = match ($assetCriticality) {
                'mission_critical' => 'emergency',
                'high' => 'high',
                default => 'medium',
            };

            FacilityWorkOrder::create([
                'tenant_id' => $schedule->tenant_id,
                'work_order_number' => 'WO-PM-'.mb_strtoupper(Str::random(6)),
                'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
                'facility_asset_id' => $schedule->facility_asset_id,
                'facility_building_id' => $schedule->asset?->facility_building_id,
                'facility_space_id' => $schedule->asset?->facility_space_id,
                'preventive_maintenance_schedule_id' => $schedule->id,
                'requester_id' => (string) $requesterId,
                'category' => $assetCategory,
                'title' => 'Preventive Maintenance: '.$schedule->title,
                'description' => $schedule->description ?? "Scheduled preventive maintenance for asset {$assetName} ({$assetTag}).",
                'priority' => $priority,
                'status' => FacilityWorkOrder::STATUS_OPEN,
                'due_date' => $targetDueDate,
                'scheduled_due_date' => $targetDueDate,
            ]);

            // Advance schedule to next interval
            $schedule->last_performed_at = Carbon::now();
            $intervalDays = $schedule->interval_days > 0
                ? $schedule->interval_days
                : match ($schedule->frequency) {
                    'daily' => 1,
                    'weekly' => 7,
                    'monthly' => 30,
                    'quarterly' => 90,
                    'biannual' => 180,
                    'annual' => 365,
                    default => 30,
                };

            $schedule->next_due_date = Carbon::parse($targetDueDate)->addDays($intervalDays);
            $schedule->save();

            $generatedCount++;
        }

        $this->info("Preventive maintenance processing complete: {$generatedCount} work order(s) generated, {$skippedCount} skipped.");

        return self::SUCCESS;
    }
}
