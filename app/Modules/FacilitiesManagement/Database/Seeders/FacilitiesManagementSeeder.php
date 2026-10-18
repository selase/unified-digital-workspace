<?php

declare(strict_types=1);

namespace App\Modules\FacilitiesManagement\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\FacilitiesManagement\Models\FacilityAsset;
use App\Modules\FacilitiesManagement\Models\FacilityBuilding;
use App\Modules\FacilitiesManagement\Models\FacilitySpace;
use App\Modules\FacilitiesManagement\Models\FacilityWorkOrder;
use App\Modules\FacilitiesManagement\Models\PreventiveMaintenanceSchedule;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class FacilitiesManagementSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::query()->where('slug', 'purpledot')->first()
                ?? Tenant::query()->where('isolation_mode', 'shared')->first();
        }

        if (! $tenant instanceof Tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('facility_buildings')) {
            return;
        }

        $manager = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $manager) {
            return;
        }

        // 1. Seed Buildings
        $bldClinical = FacilityBuilding::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'BLD-MCC',
        ], [
            'name' => 'Main Clinical Center',
            'address' => 'UGMC Medical Enclave, Gate 2, Legon Bypass, Accra',
            'floors_count' => 5,
            'total_area_sqm' => 7500.00,
            'manager_id' => (string) $manager->uuid,
            'status' => 'active',
        ]);

        $bldTower = FacilityBuilding::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'BLD-EDT',
        ], [
            'name' => 'Executive Medical & Diagnostic Tower',
            'address' => 'Independence Avenue, Ridge, Accra',
            'floors_count' => 7,
            'total_area_sqm' => 9800.00,
            'manager_id' => (string) $manager->uuid,
            'status' => 'active',
        ]);

        // 2. Seed Spaces
        FacilitySpace::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_building_id' => $bldClinical->id,
            'code' => 'SPC-MCC-01',
        ], [
            'name' => 'Diagnostic Imaging Suite',
            'floor_number' => 1,
            'space_type' => 'laboratory',
            'occupant_user_id' => (string) $manager->uuid,
            'is_occupied' => true,
        ]);

        $spaceIcu = FacilitySpace::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_building_id' => $bldClinical->id,
            'code' => 'SPC-MCC-02',
        ], [
            'name' => 'Intensive Care Unit (ICU)',
            'floor_number' => 2,
            'space_type' => 'ward',
            'occupant_user_id' => null,
            'is_occupied' => true,
        ]);

        $spacePlant = FacilitySpace::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_building_id' => $bldClinical->id,
            'code' => 'SPC-MCC-00',
        ], [
            'name' => 'Central Chiller Plant Room',
            'floor_number' => 0,
            'space_type' => 'common_area',
            'occupant_user_id' => null,
            'is_occupied' => false,
        ]);

        FacilitySpace::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_building_id' => $bldTower->id,
            'code' => 'SPC-EDT-401',
        ], [
            'name' => 'Executive Administration Suite 401',
            'floor_number' => 4,
            'space_type' => 'office',
            'occupant_user_id' => (string) $manager->uuid,
            'is_occupied' => true,
        ]);

        FacilitySpace::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_building_id' => $bldTower->id,
            'code' => 'SPC-EDT-01',
        ], [
            'name' => 'Data Center & Power Vault',
            'floor_number' => 1,
            'space_type' => 'laboratory',
            'occupant_user_id' => null,
            'is_occupied' => true,
        ]);

        // 3. Seed Assets
        $assetChiller = FacilityAsset::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'asset_tag' => 'AST-HVAC-001',
        ], [
            'facility_building_id' => $bldClinical->id,
            'facility_space_id' => $spacePlant->id,
            'name' => 'Daikin VRV IV 45kW Chiller System',
            'category' => 'hvac',
            'manufacturer' => 'Daikin Industries',
            'model_number' => 'RXYQ16T',
            'serial_number' => 'DKN-VRV-2026-99',
            'purchase_date' => now()->subMonths(6)->toDateString(),
            'purchase_cost_pesewas' => 12000000,
            'criticality' => 'mission_critical',
            'status' => 'operational',
            'last_serviced_at' => now()->subMonth(),
            'next_service_due' => now()->addMonths(2)->toDateString(),
        ]);

        $assetGenerator = FacilityAsset::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'asset_tag' => 'AST-PWR-001',
        ], [
            'facility_building_id' => $bldClinical->id,
            'facility_space_id' => $spacePlant->id,
            'name' => 'Cummins 500kVA Heavy Duty Standby Generator',
            'category' => 'electrical',
            'manufacturer' => 'Cummins Power Generation',
            'model_number' => 'C500D5e',
            'serial_number' => 'CUM-GEN-500K-01',
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost_pesewas' => 35000000,
            'criticality' => 'mission_critical',
            'status' => 'operational',
            'last_serviced_at' => now()->subWeeks(2),
            'next_service_due' => now()->addWeeks(2)->toDateString(),
        ]);

        FacilityAsset::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'asset_tag' => 'AST-MED-001',
        ], [
            'facility_building_id' => $bldClinical->id,
            'facility_space_id' => $spaceIcu->id,
            'name' => 'Philips Respironics ICU Ventilator Array',
            'category' => 'medical_device',
            'manufacturer' => 'Philips Healthcare',
            'model_number' => 'V680',
            'serial_number' => 'PH-VNT-8812',
            'purchase_date' => now()->subMonths(4)->toDateString(),
            'purchase_cost_pesewas' => 18000000,
            'criticality' => 'mission_critical',
            'status' => 'operational',
            'last_serviced_at' => now()->subWeeks(3),
            'next_service_due' => now()->addMonths(3)->toDateString(),
        ]);

        $assetElevator = FacilityAsset::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'asset_tag' => 'AST-CVL-001',
        ], [
            'facility_building_id' => $bldTower->id,
            'facility_space_id' => null,
            'name' => 'Otis Gen2 Geared Passenger Elevator',
            'category' => 'general_maintenance',
            'manufacturer' => 'Otis Elevator Co.',
            'model_number' => 'Gen2-Comfort',
            'serial_number' => 'OTIS-G2-1004',
            'purchase_date' => now()->subYears(2)->toDateString(),
            'purchase_cost_pesewas' => 22000000,
            'criticality' => 'high',
            'status' => 'operational',
            'last_serviced_at' => now()->subMonths(2),
            'next_service_due' => now()->addMonths(4)->toDateString(),
        ]);

        // 4. Seed Preventive Maintenance Schedules
        PreventiveMaintenanceSchedule::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_asset_id' => $assetChiller->id,
            'title' => 'Quarterly Refrigerant & Coil Chemical Wash',
        ], [
            'frequency' => 'quarterly',
            'interval_days' => 90,
            'description' => 'Inspect evaporator coils, chemical wash condenser fins, test refrigerant pressure gauges.',
            'next_due_date' => now()->addDays(45)->toDateString(),
            'is_active' => true,
        ]);

        PreventiveMaintenanceSchedule::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_asset_id' => $assetGenerator->id,
            'title' => 'Monthly Automatic Transfer Switch & Load Bank Test',
        ], [
            'frequency' => 'monthly',
            'interval_days' => 30,
            'description' => 'Simulate mains ECG power failure, record ATS switchover latency, test under 50% simulated building load for 30 minutes.',
            'next_due_date' => now()->addDays(7)->toDateString(),
            'is_active' => true,
        ]);

        PreventiveMaintenanceSchedule::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'facility_asset_id' => $assetElevator->id,
            'title' => 'Biannual Safety Governor & Cable Inspection',
        ], [
            'frequency' => 'biannual',
            'interval_days' => 180,
            'description' => 'Tension testing on hoisting steel cables, overspeed governor calibration, and door interlock safety certification.',
            'next_due_date' => now()->addDays(90)->toDateString(),
            'is_active' => true,
        ]);

        // 5. Seed Work Orders
        FacilityWorkOrder::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'work_order_number' => 'WO-INST-001',
        ], [
            'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
            'facility_building_id' => $bldClinical->id,
            'facility_space_id' => $spacePlant->id,
            'facility_asset_id' => $assetChiller->id,
            'requester_id' => (string) $manager->uuid,
            'assigned_technician_id' => (string) $manager->uuid,
            'category' => 'hvac',
            'title' => 'Emergency Compressor Vibration Diagnostic',
            'description' => 'Chiller compressor unit 2 reported harmonic vibration above acceptable decibel threshold during peak afternoon heat.',
            'priority' => 'high',
            'status' => FacilityWorkOrder::STATUS_IN_PROGRESS,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        FacilityWorkOrder::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'work_order_number' => 'WO-INST-002',
        ], [
            'mode' => FacilityWorkOrder::MODE_INSTITUTIONAL,
            'facility_building_id' => $bldClinical->id,
            'facility_space_id' => $spacePlant->id,
            'facility_asset_id' => $assetGenerator->id,
            'requester_id' => (string) $manager->uuid,
            'assigned_technician_id' => (string) $manager->uuid,
            'category' => 'electrical',
            'title' => 'Scheduled Monthly Standby Generator Switch Test',
            'description' => 'Automated monthly load test sequence as required by JCI accreditation infrastructure standards.',
            'priority' => 'medium',
            'status' => FacilityWorkOrder::STATUS_OPEN,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        // Mode B Occupant Marketplace Request
        if (Schema::connection($tenantConnection)->hasTable('vendor_service_jobs')) {
            $serviceJob = ServiceJob::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'job_number' => 'JOB-FAC-SEED-01',
            ], [
                'requester_id' => (string) $manager->uuid,
                'source_module' => 'facilities',
                'category' => 'plumbing',
                'title' => 'High-Pressure Booster Pipe Leakage at Staff Enclave',
                'description' => 'Main PVC pipe joint cracked near the manifold feeding staff residential apartments.',
                'location' => 'Block C Staff Enclave, Legon Bypass',
                'status' => ServiceJob::STATUS_OPEN,
            ]);

            FacilityWorkOrder::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'work_order_number' => 'WO-OCC-001',
            ], [
                'mode' => FacilityWorkOrder::MODE_OCCUPANT_MARKETPLACE,
                'facility_building_id' => $bldClinical->id,
                'facility_space_id' => null,
                'requester_id' => (string) $manager->uuid,
                'category' => 'plumbing',
                'title' => 'High-Pressure Booster Pipe Leakage at Staff Enclave',
                'description' => 'Main PVC pipe joint cracked near the manifold feeding staff residential apartments.',
                'priority' => 'medium',
                'status' => FacilityWorkOrder::STATUS_OPEN,
                'due_date' => now()->addDays(2)->toDateString(),
                'service_job_id' => $serviceJob->id,
            ]);
        }
    }
}
