<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\FacilitiesManagement\Models\FacilityAsset;
use App\Modules\FacilitiesManagement\Models\FacilityBuilding;
use App\Modules\FacilitiesManagement\Models\FacilityWorkOrder;
use App\Modules\FacilitiesManagement\Models\PreventiveMaintenanceSchedule;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createFacilitiesTestContext(): array
{
    $user = User::factory()->create([
        'first_name' => 'Facilities',
        'last_name' => 'Director',
    ]);
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'facilities.view',
        'facilities.manage',
        'facilities.assets.manage',
        'facilities.maintenance.manage',
        'facilities.occupant.request',
        'vendors.view',
        'vendors.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'category' => 'facilities-management',
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('facilities-management', $tenant);
    app(ModuleManager::class)->enableForTenant('vendor-marketplace', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/FacilitiesManagement/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/VendorMarketplace/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Phase 21: Dual-Mode Facilities Management (Institutional Asset Lifecycle & Estate Occupant Marketplace)', function (): void {
    test('manages Mode A institutional facility buildings, assets, preventive schedules and internal work orders', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        // 1. Register Building
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.buildings.store'), [
                'name' => 'UGMC Clinical Diagnostics Center',
                'code' => 'BLD-CDC',
                'address' => 'Legon Bypass, Accra',
                'floors_count' => 4,
                'total_area_sqm' => 4200.50,
            ])
            ->assertRedirect(route('facilities-management.index'));

        $building = FacilityBuilding::query()->where('code', 'BLD-CDC')->firstOrFail();
        expect($building->manager_id)->toBe((string) $user->uuid);

        // 2. Register Institutional Asset (Mode A)
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.assets.store'), [
                'facility_building_id' => $building->id,
                'name' => 'Daikin VRV IV Central Chiller Unit',
                'category' => 'hvac',
                'serial_number' => 'DKN-VRV-9921',
                'manufacturer' => 'Daikin Industries',
                'model_number' => 'RXYQ16T',
                'purchase_date' => '2026-01-15',
                'purchase_cost_ghs' => 85000.00,
                'criticality' => 'mission_critical',
            ])
            ->assertRedirect(route('facilities-management.index'));

        $asset = FacilityAsset::query()->where('serial_number', 'DKN-VRV-9921')->firstOrFail();
        expect($asset->purchase_cost_pesewas)->toBe(8500000)
            ->and($asset->status)->toBe('operational');

        // 3. Schedule Preventive Maintenance
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.assets.schedules.store', $asset), [
                'title' => 'Quarterly Compressor Oil & Refrigerant Pressure Service',
                'frequency' => 'quarterly',
                'interval_days' => 90,
                'description' => 'Check evaporator coils, compressor oil levels and replace high-pressure relief valves.',
            ])
            ->assertRedirect(route('facilities-management.index'));

        $schedule = PreventiveMaintenanceSchedule::query()->where('facility_asset_id', $asset->id)->firstOrFail();
        expect($schedule->interval_days)->toBe(90)
            ->and($schedule->is_active)->toBeTrue()
            ->and($schedule->next_due_date->toDateString())->toBe(now()->addDays(90)->toDateString());

        // 4. Create Institutional Work Order (Mode A)
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.institutional.store'), [
                'facility_building_id' => $building->id,
                'facility_asset_id' => $asset->id,
                'title' => 'Emergency Compressor Vibration Inspection',
                'category' => 'hvac',
                'priority' => 'high',
                'description' => 'Abnormal vibration detected on roof compressor fan 2.',
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect(route('facilities-management.index'));

        $workOrder = FacilityWorkOrder::query()->where('facility_asset_id', $asset->id)->firstOrFail();
        expect($workOrder->mode)->toBe(FacilityWorkOrder::MODE_INSTITUTIONAL)
            ->and($workOrder->status)->toBe(FacilityWorkOrder::STATUS_OPEN);

        // 5. Complete Work Order
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.complete', $workOrder), [
                'completion_notes' => 'Inspected vibration dampeners, replaced worn motor bushing, tested at full load.',
            ])
            ->assertRedirect(route('facilities-management.index'));

        $workOrder->refresh();
        expect($workOrder->status)->toBe(FacilityWorkOrder::STATUS_COMPLETED)
            ->and($workOrder->completed_at)->not->toBeNull();
    });

    test('dispatches Mode B estate occupant artisan requests directly into Vendor Marketplace with seamless job linkage', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        // Estate occupant logs a request for an electrician
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.occupant.store'), [
                'category' => 'electrical',
                'title' => 'Automatic Transfer Switch (ATS) Tripping Repair',
                'location' => 'Apartment 4B, East Ridge Court',
                'description' => 'ATS fails to switch cleanly back to grid when ECG mains power is restored.',
            ])
            ->assertRedirect(route('facilities-management.index'));

        $workOrder = FacilityWorkOrder::query()
            ->where('mode', FacilityWorkOrder::MODE_OCCUPANT_MARKETPLACE)
            ->where('category', 'electrical')
            ->firstOrFail();

        expect($workOrder->service_job_id)->not->toBeNull();

        // Verify companion ServiceJob created in VendorMarketplace
        $serviceJob = ServiceJob::query()->findOrFail($workOrder->service_job_id);
        expect($serviceJob->source_module)->toBe('facilities')
            ->and($serviceJob->category)->toBe('electrical')
            ->and($serviceJob->title)->toBe('Automatic Transfer Switch (ATS) Tripping Repair')
            ->and($serviceJob->location)->toBe('Apartment 4B, East Ridge Court');

        // Complete work order updates both work order and linked service job
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.complete', $workOrder), [
                'completion_notes' => 'Certified electrician recalibrated ATS relay timer; system tested through 3 full power cycles.',
            ])
            ->assertRedirect(route('facilities-management.index'));

        $workOrder->refresh();
        $serviceJob->refresh();

        expect($workOrder->status)->toBe(FacilityWorkOrder::STATUS_COMPLETED)
            ->and($serviceJob->status)->toBe(ServiceJob::STATUS_COMPLETED);
    });

    test('adversarial negative tests enforce RBAC and cross-tenant isolation on facilities management', function (): void {
        [$userA, $tenantA] = createFacilitiesTestContext();

        $otherTenant = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $foreignBuilding = FacilityBuilding::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Tenant Hospital',
            'code' => 'BLD-FRGN',
            'floors_count' => 2,
            'total_area_sqm' => 1000,
        ]);

        // Attempting to create an asset against foreign building returns 422
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('facilities-management.assets.store'), [
                'facility_building_id' => $foreignBuilding->id,
                'name' => 'Attempted Cross-Tenant Asset',
                'category' => 'electrical',
                'criticality' => 'low',
            ])
            ->assertSessionHasErrors('facility_building_id');

        // Unpermitted user receives 403
        $unpermitted = User::factory()->create(['tenant_id' => $tenantA->id]);
        $unpermitted->tenants()->syncWithoutDetaching([$tenantA->id]);

        $this->actingAs($unpermitted)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('facilities-management.index'))
            ->assertForbidden();
    });
});
