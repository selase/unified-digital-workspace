<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\FacilitiesManagement\Models\FacilityAsset;
use App\Modules\FacilitiesManagement\Models\FacilityBuilding;
use App\Modules\FacilitiesManagement\Models\FacilitySpace;
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

    test('supports complete building lifecycle: detail, edit, spaces breakdown, and archive', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        $building = FacilityBuilding::create([
            'name' => 'Cardiothoracic Center',
            'code' => 'BLD-CTC',
            'floors_count' => 4,
            'total_area_sqm' => 3800.00,
            'address' => 'Korle Bu Teaching Hospital Enclave',
            'manager_id' => (string) $user->uuid,
            'status' => 'active',
        ]);

        // 1. Show building detail
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('facilities-management.buildings.show', $building->uuid))
            ->assertOk()
            ->assertSee('Cardiothoracic Center')
            ->assertSee('BLD-CTC')
            ->assertSee('3,800.00 sqm');

        // 2. Update building
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('facilities-management.buildings.update', $building->uuid), [
                'name' => 'National Cardiothoracic Center of Excellence',
                'code' => 'BLD-NCCTC',
                'floors_count' => 5,
                'total_area_sqm' => 4200.00,
                'status' => 'maintenance',
                'address' => 'Korle Bu Medical Enclave, Gate 1',
            ])
            ->assertRedirect(route('facilities-management.buildings.show', $building->uuid));

        $building->refresh();
        expect($building->name)->toBe('National Cardiothoracic Center of Excellence')
            ->and($building->code)->toBe('BLD-NCCTC')
            ->and($building->floors_count)->toBe(5)
            ->and((float) $building->total_area_sqm)->toBe(4200.00)
            ->and($building->status)->toBe('maintenance');

        // 3. Archive / soft-delete building
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('facilities-management.buildings.destroy', $building->uuid))
            ->assertRedirect(route('facilities-management.index'));

        expect(FacilityBuilding::query()->find($building->id))->toBeNull()
            ->and(FacilityBuilding::withTrashed()->find($building->id)?->trashed())->toBeTrue();
    });

    test('supports space creation and deletion within building boundary', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        $building = FacilityBuilding::create([
            'name' => 'Maternity & Neonatal Ward',
            'code' => 'BLD-MAT',
            'floors_count' => 3,
            'total_area_sqm' => 2500.00,
        ]);

        // 1. Create Space
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.buildings.spaces.store', $building->uuid), [
                'name' => 'Neonatal Intensive Care Unit (NICU)',
                'code' => 'NICU-01',
                'floor_number' => 2,
                'space_type' => 'ward',
                'occupant_user_id' => (string) $user->uuid,
            ])
            ->assertRedirect(route('facilities-management.buildings.show', $building->uuid));

        $space = FacilitySpace::query()->where('code', 'NICU-01')->firstOrFail();
        expect($space->facility_building_id)->toBe($building->id)
            ->and($space->is_occupied)->toBeTrue()
            ->and($space->occupant_user_id)->toBe((string) $user->uuid);

        // 2. Reject duplicate space code within same building
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.buildings.spaces.store', $building->uuid), [
                'name' => 'Another NICU Room',
                'code' => 'NICU-01',
                'floor_number' => 2,
                'space_type' => 'ward',
            ])
            ->assertSessionHasErrors('code');

        // 3. Delete Space
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('facilities-management.spaces.destroy', $space->uuid))
            ->assertRedirect(route('facilities-management.buildings.show', $building->uuid));

        expect(FacilitySpace::query()->find($space->id))->toBeNull();
    });

    test('supports asset lifecycle: detail view, specifications update, space relocation, and archive', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        $building = FacilityBuilding::create([
            'name' => 'General Clinical Tower',
            'code' => 'BLD-GCT',
            'floors_count' => 3,
            'total_area_sqm' => 3000.00,
        ]);

        $spaceA = FacilitySpace::create([
            'facility_building_id' => $building->id,
            'name' => 'Radiology Suite A',
            'code' => 'RAD-A',
            'floor_number' => 1,
            'space_type' => 'laboratory',
        ]);

        $spaceB = FacilitySpace::create([
            'facility_building_id' => $building->id,
            'name' => 'Radiology Suite B',
            'code' => 'RAD-B',
            'floor_number' => 1,
            'space_type' => 'laboratory',
        ]);

        $asset = FacilityAsset::create([
            'facility_building_id' => $building->id,
            'facility_space_id' => $spaceA->id,
            'asset_tag' => 'AST-XRAY-01',
            'name' => 'Siemens Multix Impact Digital X-Ray',
            'category' => 'medical_device',
            'serial_number' => 'SMN-XR-5510',
            'manufacturer' => 'Siemens Healthineers',
            'model_number' => 'Multix-Impact',
            'purchase_date' => '2026-02-01',
            'purchase_cost_pesewas' => 25000000,
            'criticality' => 'mission_critical',
            'status' => 'operational',
        ]);

        // 1. Show asset detail
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('facilities-management.assets.show', $asset->uuid))
            ->assertOk()
            ->assertSee('Siemens Multix Impact Digital X-Ray')
            ->assertSee('AST-XRAY-01')
            ->assertSee('GHS 250,000.00')
            ->assertSee('Radiology Suite A');

        // 2. Update specifications and relocate to spaceB
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('facilities-management.assets.update', $asset->uuid), [
                'name' => 'Siemens Multix Impact Digital X-Ray (Upgraded)',
                'category' => 'medical_device',
                'criticality' => 'mission_critical',
                'status' => 'under_maintenance',
                'serial_number' => 'SMN-XR-5510-REV2',
                'manufacturer' => 'Siemens Healthineers AG',
                'model_number' => 'Multix-Impact-v2',
                'purchase_date' => '2026-02-01',
                'purchase_cost_ghs' => 275000.00,
                'facility_space_id' => $spaceB->id,
            ])
            ->assertRedirect(route('facilities-management.assets.show', $asset->uuid));

        $asset->refresh();
        expect($asset->name)->toBe('Siemens Multix Impact Digital X-Ray (Upgraded)')
            ->and($asset->facility_space_id)->toBe($spaceB->id)
            ->and($asset->status)->toBe('under_maintenance')
            ->and($asset->purchase_cost_pesewas)->toBe(27500000)
            ->and($asset->serial_number)->toBe('SMN-XR-5510-REV2');

        // 3. Soft-delete / archive asset
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('facilities-management.assets.destroy', $asset->uuid))
            ->assertRedirect(route('facilities-management.index'));

        expect(FacilityAsset::query()->find($asset->id))->toBeNull()
            ->and(FacilityAsset::withTrashed()->find($asset->id)?->trashed())->toBeTrue();
    });

    test('manages preventive maintenance schedule activation toggle and deletion', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        $building = FacilityBuilding::create([
            'name' => 'Power House Substation',
            'code' => 'BLD-PWR',
            'floors_count' => 1,
            'total_area_sqm' => 600.00,
        ]);

        $asset = FacilityAsset::create([
            'facility_building_id' => $building->id,
            'asset_tag' => 'AST-UPS-01',
            'name' => 'Eaton 120kVA Modular Online UPS',
            'category' => 'electrical',
            'criticality' => 'mission_critical',
            'status' => 'operational',
        ]);

        $schedule = PreventiveMaintenanceSchedule::create([
            'facility_asset_id' => $asset->id,
            'title' => 'Bi-Monthly Battery Bank Impedance & Voltage Test',
            'frequency' => 'monthly',
            'interval_days' => 60,
            'next_due_date' => now()->addDays(60)->toDateString(),
            'is_active' => true,
        ]);

        // 1. Toggle to pause
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.schedules.toggle', $schedule->uuid))
            ->assertRedirect(route('facilities-management.assets.show', $asset->uuid));

        $schedule->refresh();
        expect($schedule->is_active)->toBeFalse();

        // 2. Toggle back to active
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.schedules.toggle', $schedule->uuid))
            ->assertRedirect(route('facilities-management.assets.show', $asset->uuid));

        $schedule->refresh();
        expect($schedule->is_active)->toBeTrue();

        // 3. Delete schedule
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('facilities-management.schedules.destroy', $schedule->uuid))
            ->assertRedirect(route('facilities-management.assets.show', $asset->uuid));

        expect(PreventiveMaintenanceSchedule::query()->find($schedule->id))->toBeNull();
    });

    test('manages work order detail view, technician assignment, and status transitions', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();
        $technician = User::factory()->create([
            'first_name' => 'Kwame',
            'last_name' => 'Ansah',
            'tenant_id' => $tenant->id,
        ]);
        $technician->tenants()->syncWithoutDetaching([$tenant->id]);

        $building = FacilityBuilding::create([
            'name' => 'Main Engineering Depot',
            'code' => 'BLD-DEP',
            'floors_count' => 2,
            'total_area_sqm' => 1200.00,
        ]);

        $workOrder = FacilityWorkOrder::create([
            'work_order_number' => 'WO-INST-9901',
            'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
            'facility_building_id' => $building->id,
            'requester_id' => (string) $user->uuid,
            'category' => 'electrical',
            'title' => 'Emergency Main Feeder Breaker Trip',
            'description' => 'Feeder 3 tripped under heavy industrial laundry machine load.',
            'priority' => 'emergency',
            'status' => FacilityWorkOrder::STATUS_OPEN,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        // 1. Show work order detail
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('facilities-management.work-orders.show', $workOrder->uuid))
            ->assertOk()
            ->assertSee('WO-INST-9901')
            ->assertSee('Emergency Main Feeder Breaker Trip')
            ->assertSee('Main Engineering Depot');

        // 2. Assign technician
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.assign', $workOrder->uuid), [
                'assigned_technician_id' => (string) $technician->uuid,
            ])
            ->assertRedirect(route('facilities-management.work-orders.show', $workOrder->uuid));

        $workOrder->refresh();
        expect($workOrder->assigned_technician_id)->toBe((string) $technician->uuid)
            ->and($workOrder->status)->toBe(FacilityWorkOrder::STATUS_ASSIGNED);

        // 3. Move status to in_progress
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.status', $workOrder->uuid), [
                'status' => FacilityWorkOrder::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect(route('facilities-management.work-orders.show', $workOrder->uuid));

        $workOrder->refresh();
        expect($workOrder->status)->toBe(FacilityWorkOrder::STATUS_IN_PROGRESS);

        // 4. Complete work order
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('facilities-management.work-orders.complete', $workOrder), [
                'completion_notes' => 'Inspected thermal overload relay; replaced 250A fuse link; system normalized.',
                'redirect_to' => route('facilities-management.work-orders.show', $workOrder->uuid),
            ])
            ->assertRedirect(route('facilities-management.work-orders.show', $workOrder->uuid));

        $workOrder->refresh();
        expect($workOrder->status)->toBe(FacilityWorkOrder::STATUS_COMPLETED)
            ->and($workOrder->completion_notes)->toContain('replaced 250A fuse link');
    });

    test('enforces occupant privacy: occupant cannot view another users work order', function (): void {
        [$userA, $tenant] = createFacilitiesTestContext();

        // Create occupant user who only has occupant.request permission
        $occupant = User::factory()->create(['tenant_id' => $tenant->id]);
        $occupant->tenants()->syncWithoutDetaching([$tenant->id]);
        $occupant->givePermissionTo('facilities.occupant.request');

        $building = FacilityBuilding::create([
            'name' => 'Occupant Enclave',
            'code' => 'BLD-ENC',
            'floors_count' => 2,
            'total_area_sqm' => 900.00,
        ]);

        // Work order created by userA
        $orderA = FacilityWorkOrder::create([
            'work_order_number' => 'WO-PRIV-001',
            'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
            'facility_building_id' => $building->id,
            'requester_id' => (string) $userA->uuid,
            'category' => 'general_maintenance',
            'title' => 'Executive Office Desk Repair',
            'priority' => 'low',
            'status' => FacilityWorkOrder::STATUS_OPEN,
        ]);

        // Work order created by occupant
        $orderOccupant = FacilityWorkOrder::create([
            'work_order_number' => 'WO-PRIV-002',
            'mode' => FacilityWorkOrder::MODE_OCCUPANT_MARKETPLACE,
            'facility_building_id' => $building->id,
            'requester_id' => (string) $occupant->uuid,
            'category' => 'plumbing',
            'title' => 'Apartment Kitchen Tap Leak',
            'priority' => 'medium',
            'status' => FacilityWorkOrder::STATUS_OPEN,
        ]);

        // Occupant can view their own order
        $this->actingAs($occupant)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('facilities-management.work-orders.show', $orderOccupant->uuid))
            ->assertOk();

        // Occupant is forbidden (403) from viewing userA's order
        $this->actingAs($occupant)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('facilities-management.work-orders.show', $orderA->uuid))
            ->assertForbidden();
    });

    test('comprehensive cross-tenant isolation rejects access to foreign buildings, spaces, assets, schedules, and work orders', function (): void {
        [$userA, $tenantA] = createFacilitiesTestContext();

        // Tenant B Setup
        $tenantB = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $userB->tenants()->syncWithoutDetaching([$tenantB->id]);

        $bldB = FacilityBuilding::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B Hospital',
            'code' => 'BLD-TB',
            'floors_count' => 3,
            'total_area_sqm' => 2000.00,
        ]);

        $spaceB = FacilitySpace::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'facility_building_id' => $bldB->id,
            'name' => 'Tenant B Operating Theater',
            'code' => 'OT-B',
            'floor_number' => 1,
            'space_type' => 'ward',
        ]);

        $assetB = FacilityAsset::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'facility_building_id' => $bldB->id,
            'asset_tag' => 'AST-TB-01',
            'name' => 'Tenant B Anesthesia Workstation',
            'category' => 'medical_device',
            'criticality' => 'mission_critical',
            'status' => 'operational',
        ]);

        $scheduleB = PreventiveMaintenanceSchedule::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'facility_asset_id' => $assetB->id,
            'title' => 'Tenant B Oxygen Line Pressure Calibration',
            'frequency' => 'monthly',
            'interval_days' => 30,
            'is_active' => true,
        ]);

        $orderB = FacilityWorkOrder::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'work_order_number' => 'WO-TB-001',
            'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
            'facility_building_id' => $bldB->id,
            'requester_id' => (string) $userB->uuid,
            'category' => 'electrical',
            'title' => 'Tenant B Generator Maintenance',
            'priority' => 'high',
            'status' => FacilityWorkOrder::STATUS_OPEN,
        ]);

        // User A (in Tenant A context) attempts to access Tenant B resources:
        // 1. Building Show, Update, Delete -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('facilities-management.buildings.show', $bldB->uuid))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('facilities-management.buildings.update', $bldB->uuid), ['name' => 'Hijacked', 'code' => 'BLD-TB', 'floors_count' => 1, 'total_area_sqm' => 100, 'status' => 'active'])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('facilities-management.buildings.destroy', $bldB->uuid))
            ->assertNotFound();

        // 2. Space Delete -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('facilities-management.spaces.destroy', $spaceB->uuid))
            ->assertNotFound();

        // 3. Asset Show, Update, Delete -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('facilities-management.assets.show', $assetB->uuid))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->put(route('facilities-management.assets.update', $assetB->uuid), ['name' => 'Hijacked', 'category' => 'hvac', 'criticality' => 'low', 'status' => 'operational'])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('facilities-management.assets.destroy', $assetB->uuid))
            ->assertNotFound();

        // 4. Schedule Toggle, Delete -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('facilities-management.schedules.toggle', $scheduleB->uuid))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->delete(route('facilities-management.schedules.destroy', $scheduleB->uuid))
            ->assertNotFound();

        // 5. Work Order Show, Assign, Status, Complete -> 404
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('facilities-management.work-orders.show', $orderB->uuid))
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('facilities-management.work-orders.assign', $orderB->uuid), ['assigned_technician_id' => (string) $userA->uuid])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('facilities-management.work-orders.status', $orderB->uuid), ['status' => 'in_progress'])
            ->assertNotFound();

        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('facilities-management.work-orders.complete', $orderB), ['completion_notes' => 'Hijack resolution'])
            ->assertNotFound();
    });

    test('seeds facilities management data cleanly via FacilitiesManagementSeeder', function (): void {
        [$user, $tenant] = createFacilitiesTestContext();

        $seeder = new App\Modules\FacilitiesManagement\Database\Seeders\FacilitiesManagementSeeder();
        $seeder->run();

        expect(FacilityBuilding::query()->where('code', 'BLD-MCC')->exists())->toBeTrue()
            ->and(FacilityBuilding::query()->where('code', 'BLD-EDT')->exists())->toBeTrue()
            ->and(FacilitySpace::query()->where('code', 'SPC-MCC-01')->exists())->toBeTrue()
            ->and(FacilityAsset::query()->where('asset_tag', 'AST-HVAC-001')->exists())->toBeTrue()
            ->and(FacilityAsset::query()->where('asset_tag', 'AST-PWR-001')->exists())->toBeTrue()
            ->and(PreventiveMaintenanceSchedule::query()->where('title', 'like', '%Refrigerant%')->exists())->toBeTrue()
            ->and(FacilityWorkOrder::query()->where('work_order_number', 'WO-INST-001')->exists())->toBeTrue();
    });
});
