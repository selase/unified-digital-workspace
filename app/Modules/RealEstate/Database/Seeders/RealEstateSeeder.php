<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\InstallmentSchedule;
use App\Modules\RealEstate\Models\LandDocument;
use App\Modules\RealEstate\Models\LandParcel;
use App\Modules\RealEstate\Models\PropertyListing;
use App\Modules\RealEstate\Models\RealEstateProject;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class RealEstateSeeder extends Seeder
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

        if (! Schema::connection($tenantConnection)->hasTable('real_estate_projects')) {
            return;
        }

        $admin = User::query()->where('tenant_id', $tenant->id)->first() ?? User::query()->first();
        if (! $admin) {
            return;
        }

        // 1. Projects
        $project1 = RealEstateProject::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'ELH-PRIME',
        ], [
            'name' => 'East Legon Hills Prime Enclave',
            'location' => 'East Legon Hills, Adenta',
            'region' => 'Greater Accra',
            'total_acreage' => 45.00,
            'status' => 'active',
            'description' => 'Master planned gated residential community with underground utilities, paved roads and 24/7 security.',
        ]);

        $project2 = RealEstateProject::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'PRAM-COAST',
        ], [
            'name' => 'Prampram Coastal Breeze Sanctuary',
            'location' => 'Prampram Beachfront',
            'region' => 'Greater Accra',
            'total_acreage' => 80.00,
            'status' => 'active',
            'description' => 'Scenic beachfront mixed-use development with clean registered title and resort access.',
        ]);

        // 2. Land Parcels
        $parcel1 = LandParcel::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'real_estate_project_id' => $project1->id,
            'plot_number' => 'ELH-PLT-101',
        ], [
            'block_number' => 'BLK-A',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 15000000, // 150,000 GHS
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_READY_TO_BUILD,
            'status' => 'allocated',
            'min_lat' => 5.6800000,
            'max_lat' => 5.6850000,
            'min_lng' => -0.1200000,
            'max_lng' => -0.1150000,
            'search_issue_date' => now()->subDays(12)->toDateString(),
            'lands_commission_search_number' => 'LC-GAR-2026-1011',
            'is_due_diligence_verified' => true,
            'indenture_reference' => 'IND-2026-ELH-101',
            'land_title_certificate_number' => 'GA-LTD-88912',
            'stamp_duty_paid' => true,
            'pillars_and_wall_secured' => true,
            'architect_gia_number' => 'GIA-2024/09',
            'structural_engineer_ghie_number' => 'GhIE-STR-2026-04',
            'mmda_permit_number' => 'NMA-BP-2026-88',
            'is_permit_active' => true,
        ]);

        $parcel2 = LandParcel::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'real_estate_project_id' => $project1->id,
            'plot_number' => 'ELH-PLT-102',
        ], [
            'block_number' => 'BLK-A',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 15000000,
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'status' => 'available',
            'min_lat' => 5.6860000,
            'max_lat' => 5.6910000,
            'min_lng' => -0.1200000,
            'max_lng' => -0.1150000,
            'search_issue_date' => now()->subDays(8)->toDateString(),
            'lands_commission_search_number' => 'LC-GAR-2026-1012',
            'is_due_diligence_verified' => true,
        ]);

        $parcel3 = LandParcel::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'real_estate_project_id' => $project2->id,
            'plot_number' => 'PRM-PLT-501',
        ], [
            'block_number' => 'BLK-C',
            'size_dimension' => '100x100 ft',
            'acreage' => 0.2300,
            'price_pesewas' => 22000000, // 220,000 GHS
            'tenure_type' => 'STOOL',
            'current_stage' => LandParcel::STAGE_REGISTRATION,
            'status' => 'available',
            'min_lat' => 5.7100000,
            'max_lat' => 5.7160000,
            'min_lng' => 0.0500000,
            'max_lng' => 0.0560000,
            'search_issue_date' => now()->subDays(15)->toDateString(),
            'lands_commission_search_number' => 'LC-GAR-2026-9041',
            'has_traditional_consent_letter' => true,
            'is_due_diligence_verified' => true,
            'indenture_reference' => 'IND-2026-PRM-501',
            'stamp_duty_paid' => true,
            'pillars_and_wall_secured' => true,
        ]);

        // 3. Client Plot Allocation & Installments
        $allocation = ClientPlotAllocation::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'allocation_number' => 'ALC-ELH-001',
        ], [
            'land_parcel_id' => $parcel1->id,
            'client_user_id' => (string) $admin->uuid,
            'total_price_pesewas' => 15000000,
            'amount_paid_pesewas' => 7500000,
            'status' => 'active',
            'allocated_at' => now()->subMonths(2),
        ]);

        InstallmentSchedule::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'client_plot_allocation_id' => $allocation->id,
            'installment_number' => 1,
        ], [
            'due_date' => now()->subMonth()->toDateString(),
            'amount_due_pesewas' => 7500000,
            'amount_paid_pesewas' => 7500000,
            'status' => 'paid',
            'paid_at' => now()->subMonth(),
            'payment_reference' => 'PAY-ELH-001',
        ]);

        InstallmentSchedule::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'client_plot_allocation_id' => $allocation->id,
            'installment_number' => 2,
        ], [
            'due_date' => now()->addMonth()->toDateString(),
            'amount_due_pesewas' => 7500000,
            'amount_paid_pesewas' => 0,
            'status' => 'pending',
        ]);

        // 4. Property Listings
        PropertyListing::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'prime-70x100-residential-plot-east-legon-hills',
        ], [
            'land_parcel_id' => $parcel2->id,
            'title' => 'Prime 70x100 Residential Plot in East Legon Hills',
            'location' => 'East Legon Hills, Adenta, Accra',
            'description' => 'Fully titled residential parcel in a luxury gated community with 24/7 security, paved roads and ready electricity.',
            'price_pesewas' => 15000000,
            'size_dimension' => '70x100 ft',
            'tenure_type' => 'PRIVATE',
            'compliance_stage' => 'PROJECT_READY_FOR_BUILD',
            'is_featured' => true,
            'is_published' => true,
        ]);

        PropertyListing::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'scenic-100x100-beachfront-plot-prampram',
        ], [
            'land_parcel_id' => $parcel3->id,
            'title' => 'Scenic 100x100 Beachfront Plot in Prampram',
            'location' => 'Prampram Beach Road, Greater Accra',
            'description' => 'Spectacular ocean-view land parcel registered with Lands Commission, traditional authority consent letter executed.',
            'price_pesewas' => 22000000,
            'size_dimension' => '100x100 ft',
            'tenure_type' => 'STOOL',
            'compliance_stage' => 'PURCHASE_AND_REGISTRATION',
            'is_featured' => true,
            'is_published' => true,
        ]);

        // 5. Document Vault
        LandDocument::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'land_parcel_id' => $parcel1->id,
            'title' => 'Lands Commission Cadastral Search Certificate',
        ], [
            'workflow_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'document_type' => 'SEARCH_REPORT',
            'file_path' => "tenants/{$tenant->id}/real-estate/{$parcel1->uuid}/search_cert.pdf",
            'verification_status' => 'VERIFIED',
            'uploaded_by_id' => (string) $admin->uuid,
            'verified_by_id' => (string) $admin->uuid,
        ]);
    }
}
