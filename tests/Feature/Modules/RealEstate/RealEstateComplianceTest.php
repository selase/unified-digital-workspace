<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\LandDocument;
use App\Modules\RealEstate\Models\LandParcel;
use App\Modules\RealEstate\Models\PropertyListing;
use App\Modules\RealEstate\Models\RealEstateProject;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createRealEstateTestContext(): array
{
    $user = User::factory()->create([
        'first_name' => 'RealEstate',
        'last_name' => 'Registrar',
    ]);
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'realestate.view',
        'realestate.manage',
        'realestate.compliance.verify',
        'realestate.client.portal',
        'realestate.documents.manage',
        'vendors.manage',
        'crm.view',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'category' => 'real-estate',
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('real-estate', $tenant);
    app(ModuleManager::class)->enableForTenant('vendor-marketplace', $tenant);
    app(ModuleManager::class)->enableForTenant('crm', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/RealEstate/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/VendorMarketplace/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Crm/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Phase 20: Real Estate Management, Public Land Listings, Client Portal, Legacy Vault & Ghana Compliance Lifecycle', function (): void {
    test('enforces spatial overlap detection to prevent double-allocation of land parcels', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'East Legon Hills Prime Enclave',
            'code' => 'ELH-PRIME',
            'location' => 'East Legon Hills',
            'region' => 'Greater Accra',
            'total_acreage' => 50.00,
        ]);

        // Register initial Plot A
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.store'), [
                'real_estate_project_id' => $project->id,
                'plot_number' => 'PLT-001',
                'size_dimension' => '70x100 ft',
                'acreage' => 0.1600,
                'price_ghs' => 150000,
                'tenure_type' => 'PRIVATE',
                'min_lat' => 5.6800000,
                'max_lat' => 5.6850000,
                'min_lng' => -0.1200000,
                'max_lng' => -0.1150000,
            ])
            ->assertRedirect(route('real-estate.index'));

        expect(LandParcel::query()->where('plot_number', 'PLT-001')->exists())->toBeTrue();

        // Attempting to register overlapping Plot B MUST fail with a spatial error
        $overlapResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.store'), [
                'real_estate_project_id' => $project->id,
                'plot_number' => 'PLT-002-OVERLAP',
                'size_dimension' => '70x100 ft',
                'acreage' => 0.1600,
                'price_ghs' => 150000,
                'tenure_type' => 'PRIVATE',
                'min_lat' => 5.6820000, // Overlaps between 5.6800000 and 5.6850000
                'max_lat' => 5.6870000,
                'min_lng' => -0.1180000, // Overlaps between -0.1200000 and -0.1150000
                'max_lng' => -0.1120000,
            ]);

        $overlapResponse->assertSessionHasErrors('spatial');
        expect(LandParcel::query()->where('plot_number', 'PLT-002-OVERLAP')->exists())->toBeFalse();

        // Non-overlapping Plot C succeeds
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.store'), [
                'real_estate_project_id' => $project->id,
                'plot_number' => 'PLT-003-CLEAR',
                'size_dimension' => '70x100 ft',
                'acreage' => 0.1600,
                'price_ghs' => 150000,
                'tenure_type' => 'PRIVATE',
                'min_lat' => 5.7000000,
                'max_lat' => 5.7050000,
                'min_lng' => -0.1000000,
                'max_lng' => -0.0950000,
            ])
            ->assertRedirect(route('real-estate.index'));

        expect(LandParcel::query()->where('plot_number', 'PLT-003-CLEAR')->exists())->toBeTrue();
    });

    test('enforces Ghana compliance 4-stage lifecycle state machine with 30-day search freshness and GIA/GhIE validation', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'Prampram Coastal Plains',
            'code' => 'PRAM-01',
            'location' => 'Prampram',
            'region' => 'Greater Accra',
            'total_acreage' => 100.00,
        ]);

        $parcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-PRAM-42',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 9000000,
            'tenure_type' => 'STOOL', // Stool land requires traditional consent letter
            'current_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'status' => 'available',
        ]);

        // 1. Due diligence transition without consent letter is rejected
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.compliance.update', $parcel), [
                'inspection_date' => now()->toDateString(),
                'lands_commission_search_number' => 'LC-GAR-2026-9901',
                'search_issue_date' => now()->subDays(5)->toDateString(),
                'is_due_diligence_verified' => 1,
                'has_traditional_consent_letter' => 0, // Missing!
            ])
            ->assertRedirect(route('real-estate.index'));

        $blockedAttempt = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', $parcel), [
                'target_stage' => LandParcel::STAGE_REGISTRATION,
            ]);
        $blockedAttempt->assertSessionHasErrors('stage');

        // 2. Due diligence transition with expired search (>30 days) is rejected
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.compliance.update', $parcel), [
                'inspection_date' => now()->toDateString(),
                'lands_commission_search_number' => 'LC-GAR-2026-9901',
                'search_issue_date' => now()->subDays(35)->toDateString(), // Stale!
                'is_due_diligence_verified' => 1,
                'has_traditional_consent_letter' => 1,
            ]);

        $staleAttempt = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', $parcel), [
                'target_stage' => LandParcel::STAGE_REGISTRATION,
            ]);
        $staleAttempt->assertSessionHasErrors('stage');

        // 3. Due diligence with valid fresh search (5 days old) + traditional consent succeeds
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.compliance.update', $parcel), [
                'inspection_date' => now()->toDateString(),
                'lands_commission_search_number' => 'LC-GAR-2026-9901',
                'search_issue_date' => now()->subDays(5)->toDateString(),
                'is_due_diligence_verified' => 1,
                'has_traditional_consent_letter' => 1,
            ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', $parcel), [
                'target_stage' => LandParcel::STAGE_REGISTRATION,
            ])
            ->assertRedirect(route('real-estate.index'));

        $parcel->refresh();
        expect($parcel->current_stage)->toBe(LandParcel::STAGE_REGISTRATION);

        // Verify boundary risk warning is flagged when pillars are unbuilt
        expect($parcel->hasUnsecuredBoundaryRisk())->toBeTrue();

        // 4. Update Registration details & pillars wall
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.compliance.update', $parcel), [
                'indenture_reference' => 'IND-2026-PRAM-42',
                'land_title_certificate_number' => 'GA-LTD-44091',
                'stamp_duty_paid' => 1,
                'pillars_and_wall_secured' => 1,
                'registration_date' => now()->toDateString(),
            ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', $parcel), [
                'target_stage' => LandParcel::STAGE_PERMITTING,
            ])
            ->assertRedirect(route('real-estate.index'));

        $parcel->refresh();
        expect($parcel->current_stage)->toBe(LandParcel::STAGE_PERMITTING)
            ->and($parcel->hasUnsecuredBoundaryRisk())->toBeFalse();

        // 5. Permitting: Invalid Architect/Engineer format is rejected
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.compliance.update', $parcel), [
                'architect_gia_number' => 'INVALID_GIA_NUM',
                'structural_engineer_ghie_number' => 'GhIE-STR-2026-04',
                'mmda_permit_number' => 'NMA-BP-2026-88',
                'is_permit_active' => 1,
            ]);

        $invalidPermit = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', $parcel), [
                'target_stage' => LandParcel::STAGE_READY_TO_BUILD,
            ]);
        $invalidPermit->assertSessionHasErrors('stage');

        // Valid GIA + GhIE format advances to PROJECT_READY_FOR_BUILD
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.compliance.update', $parcel), [
                'architect_gia_number' => 'GIA-2024/09',
                'structural_engineer_ghie_number' => 'GhIE-STR-2026-04',
                'mmda_permit_number' => 'NMA-BP-2026-88',
                'is_permit_active' => 1,
            ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', $parcel), [
                'target_stage' => LandParcel::STAGE_READY_TO_BUILD,
            ])
            ->assertRedirect(route('real-estate.index'));

        $parcel->refresh();
        expect($parcel->current_stage)->toBe(LandParcel::STAGE_READY_TO_BUILD);
    });

    test('manages client allocations, installment top-up, vendor marketplace job dispatch and private document vault security', function (): void {
        Storage::fake('local');
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'Appolonia City Heights',
            'code' => 'APP-HGT',
            'location' => 'Appolonia',
            'region' => 'Greater Accra',
            'total_acreage' => 40.00,
        ]);

        $parcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-APP-12',
            'size_dimension' => '100x100 ft',
            'acreage' => 0.2300,
            'price_pesewas' => 20000000, // GHS 200,000.00
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_REGISTRATION,
            'status' => 'available',
        ]);

        // 1. Upload private document to vault
        $dummyFile = UploadedFile::fake()->create('stamped_indenture_plt12.pdf', 1024, 'application/pdf');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.documents.store', $parcel), [
                'document_type' => 'INDENTURE',
                'title' => 'Official Lands Commission Stamped Indenture',
                'file' => $dummyFile,
            ])
            ->assertRedirect(route('real-estate.index'));

        $document = LandDocument::query()->where('land_parcel_id', $parcel->id)->firstOrFail();
        expect(Storage::disk('local')->exists($document->file_path))->toBeTrue();

        // 2. Allocate plot to client with 4 installments
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.allocate', $parcel), [
                'client_name' => 'Kofi Annan Mensah',
                'client_email' => 'kofi.mensah@gh-client.org',
                'number_of_installments' => 4,
            ])
            ->assertRedirect(route('real-estate.index'));

        $allocation = ClientPlotAllocation::query()->where('land_parcel_id', $parcel->id)->firstOrFail();
        $clientUser = User::query()->where('email', 'kofi.mensah@gh-client.org')->firstOrFail();

        expect($allocation->client_user_id)->toBe((string) $clientUser->uuid)
            ->and($allocation->total_price_pesewas)->toBe(20000000)
            ->and($allocation->installments()->count())->toBe(4);

        // 3. Client top-up / installment payment
        $firstInstallment = $allocation->installments()->firstOrFail();
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.installments.pay', $firstInstallment), [
                'amount_ghs' => 50000.00,
            ]);

        $firstInstallment->refresh();
        $allocation->refresh();
        expect($firstInstallment->status)->toBe('paid')
            ->and($firstInstallment->amount_paid_pesewas)->toBe(5000000)
            ->and($allocation->amount_paid_pesewas)->toBe(5000000);

        // 4. Order building materials from Vendor Marketplace
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.order-vendor', $parcel), [
                'category' => 'building_materials',
                'title' => 'Supply 200 Bags Ghacem Cement & 4 Trips Sand',
            ]);

        $serviceJob = ServiceJob::query()->where('source_module', 'real_estate')->firstOrFail();
        expect($serviceJob->category)->toBe('building_materials')
            ->and($serviceJob->status)->toBe(ServiceJob::STATUS_OPEN);

        // 5. Verify Private Document Vault download & Cache-Control security header
        $downloadResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.documents.download', $document));

        $downloadResponse->assertOk();
        expect((string) $downloadResponse->headers->get('Cache-Control'))
            ->toContain('private')
            ->toContain('no-store');

        // Adversarial check: unauthorized user receives 403
        $unauthorized = User::factory()->create(['tenant_id' => $tenant->id]);
        $unauthorized->tenants()->syncWithoutDetaching([$tenant->id]);

        $this->actingAs($unauthorized)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.documents.download', $document))
            ->assertForbidden();
    });

    test('public listing inquiries automatically persist into CRM leads and cross-tenant boundaries are strictly guarded', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'Oyibi Green Sanctuary',
            'code' => 'OYB-GRN',
            'location' => 'Oyibi Valley',
            'region' => 'Greater Accra',
            'total_acreage' => 15.00,
        ]);

        $listing = PropertyListing::create([
            'title' => 'Serviced 80x100 Residential Plot at Oyibi',
            'slug' => 'serviced-80x100-residential-plot-oyibi',
            'location' => 'Oyibi Valley, Greater Accra',
            'description' => 'Fully registered estate plot with water, electricity and ready MMDA permit.',
            'price_pesewas' => 18000000, // GHS 180,000
            'size_dimension' => '80x100 ft',
            'tenure_type' => 'PRIVATE',
            'compliance_stage' => 'PROJECT_READY_FOR_BUILD',
            'is_published' => true,
        ]);

        // Submit public property inquiry
        $inquiryResponse = $this->post(route('real-estate.public.inquire', $listing), [
            'full_name' => 'Mrs. Beatrice Boateng',
            'email' => 'beatrice.boateng@gh-diaspora.co.uk',
            'phone' => '+447911122233',
            'message' => 'Relocating to Ghana next year; looking to acquire two adjoining plots.',
        ]);

        $inquiryResponse->assertRedirect();

        $crmLead = CrmLead::query()->where('email', 'beatrice.boateng@gh-diaspora.co.uk')->first();
        expect($crmLead)->not->toBeNull()
            ->and($crmLead?->source)->toBe('website_enquiry')
            ->and($crmLead?->enquiry_type)->toBe('real_estate_listing')
            ->and($crmLead?->full_name)->toBe('Mrs. Beatrice Boateng');

        // Adversarial Cross-Tenant Test: Foreign tenant parcel is rejected (404)
        $foreignTenant = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $foreignProject = RealEstateProject::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'name' => 'Foreign Enclave',
            'code' => 'FRGN-ENCLAVE',
            'location' => 'Foreign Region',
            'region' => 'Central Region',
            'total_acreage' => 20.00,
        ]);

        $foreignParcel = LandParcel::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'real_estate_project_id' => $foreignProject->id,
            'plot_number' => 'FOREIGN-PLT-01',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 10000000,
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.transition', ['parcel' => $foreignParcel->uuid]), [
                'target_stage' => LandParcel::STAGE_REGISTRATION,
            ])
            ->assertNotFound();
    });
});
