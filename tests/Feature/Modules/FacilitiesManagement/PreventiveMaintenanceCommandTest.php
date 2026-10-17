<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\FacilitiesManagement\Models\FacilityAsset;
use App\Modules\FacilitiesManagement\Models\FacilityBuilding;
use App\Modules\FacilitiesManagement\Models\FacilityWorkOrder;
use App\Modules\FacilitiesManagement\Models\PreventiveMaintenanceSchedule;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

describe('P1-4: Facilities Preventive Maintenance Work Order Generator Command', function () {
    test('facilities:generate-preventive-work-orders creates work order for due schedules and is idempotent', function () {
        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('facilities-management', $tenant);

        $building = FacilityBuilding::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Hospital Block',
            'code' => 'BLD-'.Str::random(4),
            'floors_count' => 5,
            'total_area_sqm' => 12500,
            'manager_id' => (string) $user->uuid,
            'status' => 'active',
        ]);

        $asset = FacilityAsset::create([
            'tenant_id' => $tenant->id,
            'facility_building_id' => $building->id,
            'asset_tag' => 'AST-'.Str::random(6),
            'name' => 'Main Chiller Unit 1',
            'category' => 'hvac',
            'criticality' => 'mission_critical',
            'status' => 'operational',
        ]);

        $initialDueDate = now()->subDay()->toDateString();

        $schedule = PreventiveMaintenanceSchedule::create([
            'tenant_id' => $tenant->id,
            'facility_asset_id' => $asset->id,
            'title' => 'Quarterly Compressor Overhaul',
            'description' => 'Inspect refrigerant pressures and lubrication levels.',
            'frequency' => 'quarterly',
            'interval_days' => 90,
            'next_due_date' => $initialDueDate,
            'is_active' => true,
        ]);

        // 1. Run the command first time
        $exitCode = Artisan::call('facilities:generate-preventive-work-orders', [
            '--tenant' => (string) $tenant->id,
        ]);

        expect($exitCode)->toBe(0);

        // Assert work order created
        $workOrder = FacilityWorkOrder::where('preventive_maintenance_schedule_id', $schedule->id)->first();
        expect($workOrder)->not->toBeNull()
            ->and($workOrder->facility_asset_id)->toBe($asset->id)
            ->and($workOrder->facility_building_id)->toBe($building->id)
            ->and($workOrder->scheduled_due_date->toDateString())->toBe($initialDueDate)
            ->and($workOrder->priority)->toBe('emergency') // mission_critical -> emergency
            ->and($workOrder->status)->toBe('open');

        // Assert schedule was advanced by 90 days
        $schedule->refresh();
        $expectedNextDate = Carbon::parse($initialDueDate)->addDays(90)->toDateString();
        expect($schedule->next_due_date->toDateString())->toBe($expectedNextDate)
            ->and($schedule->last_performed_at)->not->toBeNull();

        // 2. Run the command a second time immediately -> must be idempotent, skip creation
        $secondExitCode = Artisan::call('facilities:generate-preventive-work-orders', [
            '--tenant' => (string) $tenant->id,
        ]);

        expect($secondExitCode)->toBe(0);

        // Assert count of work orders is still exactly 1
        $count = FacilityWorkOrder::where('preventive_maintenance_schedule_id', $schedule->id)->count();
        expect($count)->toBe(1);
    });
});
