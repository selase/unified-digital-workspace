<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\InstallmentSchedule;
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

    test('enforces adversarial security boundaries against IDOR on documents, expert orders, and installments, and rejects overpayments', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        // 1. Setup client A and client B
        $clientA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'clientA@example.com',
        ]);
        $clientB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'clientB@example.com',
        ]);

        $clientA->tenants()->syncWithoutDetaching([$tenant->id]);
        $clientB->tenants()->syncWithoutDetaching([$tenant->id]);

        $clientA->givePermissionTo('realestate.client.portal');
        $clientB->givePermissionTo('realestate.client.portal');

        $project = RealEstateProject::create([
            'name' => 'Legacy Hills',
            'code' => 'LGC-HILLS',
            'location' => 'Prampram',
            'region' => 'Greater Accra',
            'total_acreage' => 10.00,
            'status' => 'active',
        ]);

        $parcelA = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-A-01',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 5000000, // 50,000 GHS
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_READY_TO_BUILD,
            'status' => 'allocated',
        ]);

        $allocationA = ClientPlotAllocation::create([
            'land_parcel_id' => $parcelA->id,
            'client_user_id' => (string) $clientA->uuid,
            'allocation_number' => 'ALC-A-001',
            'total_price_pesewas' => 5000000,
            'amount_paid_pesewas' => 0,
            'status' => 'active',
            'allocated_at' => now(),
        ]);

        $installmentA = InstallmentSchedule::create([
            'client_plot_allocation_id' => $allocationA->id,
            'installment_number' => 1,
            'due_date' => now()->addMonth()->toDateString(),
            'amount_due_pesewas' => 2500000, // 25,000 GHS
            'amount_paid_pesewas' => 0,
            'status' => 'pending',
        ]);

        // Upload document for Parcel A
        Storage::fake('local');
        $fakeDocPath = "tenants/{$tenant->id}/real-estate/{$parcelA->uuid}/test_doc.pdf";
        Storage::disk('local')->put($fakeDocPath, 'Secret Indenture Content');

        $docA = LandDocument::create([
            'land_parcel_id' => $parcelA->id,
            'workflow_stage' => $parcelA->current_stage,
            'document_type' => 'INDENTURE',
            'title' => 'Indenture for Client A',
            'file_path' => $fakeDocPath,
            'verification_status' => 'VERIFIED',
            'uploaded_by_id' => (string) $user->uuid,
        ]);

        // ADVERSARIAL TEST 1: Client B cannot download Client A's document (IDOR 403)
        $this->actingAs($clientB)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.documents.download', $docA))
            ->assertForbidden();

        // But Client A can download their own document
        $this->actingAs($clientA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.documents.download', $docA))
            ->assertOk();

        // ADVERSARIAL TEST 2: Client B cannot dispatch vendor order for Client A's parcel (IDOR 403)
        $this->actingAs($clientB)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.parcels.order-vendor', $parcelA), [
                'category' => 'surveying',
                'title' => 'Unauthorized survey',
            ])
            ->assertForbidden();

        // ADVERSARIAL TEST 3: Client B cannot pay Client A's installment (IDOR 403)
        $this->actingAs($clientB)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.installments.pay', $installmentA), [
                'amount_ghs' => 5000,
                'payment_reference' => 'PAY-ILLEGAL-01',
            ])
            ->assertForbidden();

        // ADVERSARIAL TEST 4: Client A attempting to overpay installment is rejected with validation error
        $overpayResponse = $this->actingAs($clientA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.installments.pay', $installmentA), [
                'amount_ghs' => 30000, // Due is 25,000 GHS
                'payment_reference' => 'PAY-OVERPAY-01',
            ]);
        $overpayResponse->assertSessionHasErrors('amount_ghs');
        expect($installmentA->fresh()->amount_paid_pesewas)->toBe(0);

        // ADVERSARIAL TEST 5: Online payment routes to PaymentGateway checkout session
        Illuminate\Support\Facades\Http::fake([
            'api.paystack.co/*' => Illuminate\Support\Facades\Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.test/pay/session_abc123',
                    'email' => 'clientA@example.com',
                ],
            ], 200),
        ]);

        $mockGateway = Mockery::mock(PaymentGateway::class);
        $mockGateway->shouldReceive('createOneTimeCheckoutSession')
            ->andReturn('https://checkout.paystack.test/pay/session_abc123');
        app()->instance(PaymentGateway::class, $mockGateway);

        $this->actingAs($clientA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.installments.pay', $installmentA), [
                'amount_ghs' => 10000,
                'pay_online' => true,
            ])
            ->assertRedirect('https://checkout.paystack.test/pay/session_abc123');
    });

    test('manages project edit and soft-delete with authorization gates', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'Legacy Garden Enclave',
            'code' => 'LGC-GARDEN',
            'location' => 'Oyibi',
            'region' => 'Greater Accra',
            'total_acreage' => 12.50,
            'status' => 'planning',
        ]);

        $unauthorized = User::factory()->create(['tenant_id' => $tenant->id]);
        $unauthorized->tenants()->syncWithoutDetaching([$tenant->id]);

        // Unauthorized checks
        $this->actingAs($unauthorized)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.projects.edit', $project))
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('real-estate.projects.update', $project), [
                'name' => 'Hacked Enclave',
                'code' => 'HACK-01',
                'location' => 'Accra',
                'region' => 'Greater Accra',
                'total_acreage' => 10,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('real-estate.projects.destroy', $project))
            ->assertForbidden();

        // Authorized edit and update
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.projects.edit', $project))
            ->assertOk()
            ->assertSee('Legacy Garden Enclave');

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('real-estate.projects.update', $project), [
                'name' => 'Legacy Garden Enclave Phase 2',
                'code' => 'LGC-GARDEN-2',
                'location' => 'Oyibi Valley',
                'region' => 'Greater Accra',
                'total_acreage' => 20.00,
                'status' => 'active',
                'description' => 'Updated master plan for phase 2',
            ])
            ->assertRedirect(route('real-estate.index'));

        $project->refresh();
        expect($project->name)->toBe('Legacy Garden Enclave Phase 2')
            ->and($project->code)->toBe('LGC-GARDEN-2')
            ->and((float) $project->total_acreage)->toBe(20.00)
            ->and($project->status)->toBe('active');

        // Authorized soft-delete
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('real-estate.projects.destroy', $project))
            ->assertRedirect(route('real-estate.index'));

        expect($project->fresh()?->trashed())->toBeTrue();
    });

    test('manages parcel edit, spatial overlap re-validation on update, and soft-delete', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'Ridge Haven Enclave',
            'code' => 'RDG-HVN',
            'location' => 'Aburi Foothills',
            'region' => 'Eastern Region',
            'total_acreage' => 30.00,
        ]);

        $parcel1 = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-RH-01',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 12000000,
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'status' => 'available',
            'min_lat' => 5.8000000,
            'max_lat' => 5.8050000,
            'min_lng' => -0.1500000,
            'max_lng' => -0.1450000,
        ]);

        $parcel2 = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-RH-02',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 12000000,
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_DUE_DILIGENCE,
            'status' => 'available',
            'min_lat' => 5.8200000,
            'max_lat' => 5.8250000,
            'min_lng' => -0.1300000,
            'max_lng' => -0.1250000,
        ]);

        $unauthorized = User::factory()->create(['tenant_id' => $tenant->id]);
        $unauthorized->tenants()->syncWithoutDetaching([$tenant->id]);

        $this->actingAs($unauthorized)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.parcels.edit', $parcel2))
            ->assertForbidden();

        // Updating parcel 2 to overlap parcel 1 coordinates MUST trigger spatial validation failure
        $overlapUpdate = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('real-estate.parcels.update', $parcel2), [
                'real_estate_project_id' => $project->id,
                'plot_number' => 'PLT-RH-02',
                'size_dimension' => '70x100 ft',
                'acreage' => 0.1600,
                'price_ghs' => 130000,
                'tenure_type' => 'PRIVATE',
                'status' => 'available',
                'min_lat' => 5.8020000, // Conflicts with parcel 1!
                'max_lat' => 5.8070000,
                'min_lng' => -0.1480000,
                'max_lng' => -0.1420000,
            ]);

        $overlapUpdate->assertSessionHasErrors('spatial');

        // Valid update succeeds
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('real-estate.parcels.update', $parcel2), [
                'real_estate_project_id' => $project->id,
                'plot_number' => 'PLT-RH-02-MOD',
                'block_number' => 'BLK-C',
                'size_dimension' => '100x100 ft',
                'acreage' => 0.2300,
                'price_ghs' => 180000,
                'tenure_type' => 'FAMILY',
                'status' => 'reserved',
                'min_lat' => 5.8200000,
                'max_lat' => 5.8250000,
                'min_lng' => -0.1300000,
                'max_lng' => -0.1250000,
            ])
            ->assertRedirect(route('real-estate.index'));

        $parcel2->refresh();
        expect($parcel2->plot_number)->toBe('PLT-RH-02-MOD')
            ->and($parcel2->block_number)->toBe('BLK-C')
            ->and($parcel2->size_dimension)->toBe('100x100 ft')
            ->and($parcel2->tenure_type)->toBe('FAMILY')
            ->and($parcel2->status)->toBe('reserved');

        // Soft-delete parcel
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('real-estate.parcels.destroy', $parcel2))
            ->assertRedirect(route('real-estate.index'));

        expect($parcel2->fresh()?->trashed())->toBeTrue();
    });

    test('supports property listing image upload, public display, edit, and soft-delete', function (): void {
        Storage::fake('public');
        [$user, $tenant] = createRealEstateTestContext();

        $dummyImage = UploadedFile::fake()->image('plot_showcase.jpg', 800, 600);

        // 1. Create listing with image
        $createResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('real-estate.listings.store'), [
                'title' => 'Prime Waterfront Plot Prampram',
                'location' => 'Prampram Beach Road',
                'description' => 'Direct beachfront plot ready for residential villa development.',
                'price_ghs' => 250000,
                'size_dimension' => '100x100 ft',
                'tenure_type' => 'PRIVATE',
                'compliance_stage' => 'PROJECT_READY_FOR_BUILD',
                'is_featured' => 1,
                'is_published' => 1,
                'image' => $dummyImage,
            ]);

        $createResponse->assertRedirect(route('real-estate.index'));

        $listing = PropertyListing::query()->where('title', 'Prime Waterfront Plot Prampram')->firstOrFail();
        expect($listing->image_path)->not->toBeNull()
            ->and(Storage::disk('public')->exists($listing->image_path))->toBeTrue();

        // 2. Public Catalog and Show render the listing and image
        $catalogResponse = $this->get(route('real-estate.public.catalog'));
        $catalogResponse->assertOk()
            ->assertSee('Prime Waterfront Plot Prampram')
            ->assertSee(Storage::url($listing->image_path));

        $showResponse = $this->get(route('real-estate.public.show', $listing));
        $showResponse->assertOk()
            ->assertSee('Prime Waterfront Plot Prampram')
            ->assertSee(Storage::url($listing->image_path));

        // 3. Edit listing with a new image
        $replacementImage = UploadedFile::fake()->image('updated_showcase.png', 1024, 768);
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->put(route('real-estate.listings.update', $listing), [
                'title' => 'Prime Waterfront Villa Plot Prampram Updated',
                'location' => 'Prampram Beach Road, Greater Accra',
                'description' => 'Updated luxury beachfront parcel.',
                'price_ghs' => 280000,
                'size_dimension' => '100x100 ft',
                'tenure_type' => 'PRIVATE',
                'compliance_stage' => 'PROJECT_READY_FOR_BUILD',
                'is_featured' => 1,
                'is_published' => 1,
                'image' => $replacementImage,
            ])
            ->assertRedirect(route('real-estate.index'));

        $listing->refresh();
        expect($listing->title)->toBe('Prime Waterfront Villa Plot Prampram Updated')
            ->and($listing->price_pesewas)->toBe(28000000)
            ->and(Storage::disk('public')->exists($listing->image_path))->toBeTrue();

        // 4. Soft-delete listing
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->delete(route('real-estate.listings.destroy', $listing))
            ->assertRedirect(route('real-estate.index'));

        expect($listing->fresh()?->trashed())->toBeTrue();

        // Trashed listing is not shown in public catalog
        $this->flushSession();
        $this->get(route('real-estate.public.catalog'))
            ->assertDontSee('Prime Waterfront Villa Plot Prampram Updated');
    });

    test('enforces allocation payment history and printable receipts with adversarial IDOR check', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $clientA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Kojo',
            'last_name' => 'Mensah',
            'email' => 'kojo@clientA.com',
        ]);
        $clientB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Ama',
            'last_name' => 'Osei',
            'email' => 'ama@clientB.com',
        ]);

        $clientA->tenants()->syncWithoutDetaching([$tenant->id]);
        $clientB->tenants()->syncWithoutDetaching([$tenant->id]);
        $clientA->givePermissionTo('realestate.client.portal');
        $clientB->givePermissionTo('realestate.client.portal');

        $project = RealEstateProject::create([
            'name' => 'Crown Valley Enclave',
            'code' => 'CRN-VLY',
            'location' => 'Airport Hills',
            'region' => 'Greater Accra',
            'total_acreage' => 15.00,
        ]);

        $parcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-CV-07',
            'size_dimension' => '100x100 ft',
            'acreage' => 0.2300,
            'price_pesewas' => 10000000,
            'tenure_type' => 'PRIVATE',
            'current_stage' => LandParcel::STAGE_REGISTRATION,
            'status' => 'allocated',
        ]);

        $allocation = ClientPlotAllocation::create([
            'land_parcel_id' => $parcel->id,
            'client_user_id' => (string) $clientA->uuid,
            'allocation_number' => 'ALC-CV-007',
            'total_price_pesewas' => 10000000,
            'amount_paid_pesewas' => 5000000,
            'status' => 'active',
            'allocated_at' => now(),
        ]);

        // Paid installment #1
        $paidInstallment = InstallmentSchedule::create([
            'client_plot_allocation_id' => $allocation->id,
            'installment_number' => 1,
            'due_date' => now()->subMonth()->toDateString(),
            'amount_due_pesewas' => 5000000,
            'amount_paid_pesewas' => 5000000,
            'status' => 'paid',
            'paid_at' => now()->subWeeks(2),
            'payment_reference' => 'PAY-CRN-001',
        ]);

        // Unpaid installment #2
        $unpaidInstallment = InstallmentSchedule::create([
            'client_plot_allocation_id' => $allocation->id,
            'installment_number' => 2,
            'due_date' => now()->addMonth()->toDateString(),
            'amount_due_pesewas' => 5000000,
            'amount_paid_pesewas' => 0,
            'status' => 'pending',
        ]);

        // 1. Client A views their own receipt: 200 OK
        $receiptResponse = $this->actingAs($clientA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.installments.receipt', $paidInstallment));

        $receiptResponse->assertOk()
            ->assertSee('Official Payment Receipt')
            ->assertSee('PAY-CRN-001')
            ->assertSee('Kojo Mensah')
            ->assertSee('Plot PLT-CV-07');

        // 2. ADVERSARIAL IDOR TEST: Client B attempts to view Client A\'s receipt -> 403 Forbidden!
        $this->actingAs($clientB)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.installments.receipt', $paidInstallment))
            ->assertForbidden();

        // 3. Requesting receipt for an unpaid installment returns 404
        $this->actingAs($clientA)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.installments.receipt', $unpaidInstallment))
            ->assertNotFound();

        // 4. Staff with realestate.view can access receipt
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->get(route('real-estate.installments.receipt', $paidInstallment))
            ->assertOk();
    });

    test('evaluates 30-day search expiry rule and spatial overlap status badges accurately', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $project = RealEstateProject::create([
            'name' => 'Tema Golf Enclave',
            'code' => 'TMA-GLF',
            'location' => 'Tema Community 25',
            'region' => 'Greater Accra',
            'total_acreage' => 20.00,
        ]);

        // Parcel with fresh search (10 days old)
        $freshParcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-TMA-01',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 10000000,
            'tenure_type' => 'PRIVATE',
            'search_issue_date' => now()->subDays(10)->toDateString(),
            'is_legacy' => false,
            'min_lat' => 5.7000000,
            'max_lat' => 5.7050000,
            'min_lng' => -0.0100000,
            'max_lng' => -0.0050000,
        ]);

        expect($freshParcel->isSearchExpired())->toBeFalse()
            ->and($freshParcel->searchDaysRemaining())->toBe(20)
            ->and($freshParcel->checkSpatialOverlap())->toBeNull();

        // Parcel with expired search (35 days old)
        $expiredParcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-TMA-02',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 10000000,
            'tenure_type' => 'PRIVATE',
            'search_issue_date' => now()->subDays(35)->toDateString(),
            'is_legacy' => false,
            'min_lat' => 5.7200000,
            'max_lat' => 5.7250000,
            'min_lng' => -0.0200000,
            'max_lng' => -0.0150000,
        ]);

        expect($expiredParcel->isSearchExpired())->toBeTrue()
            ->and($expiredParcel->searchDaysRemaining())->toBe(0);

        // Legacy parcel is exempt from expiry
        $legacyParcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-TMA-LGC',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 10000000,
            'tenure_type' => 'PRIVATE',
            'search_issue_date' => now()->subDays(60)->toDateString(),
            'is_legacy' => true,
        ]);

        expect($legacyParcel->isSearchExpired())->toBeFalse();

        // Parcel overlapping with freshParcel
        $overlapParcel = LandParcel::create([
            'real_estate_project_id' => $project->id,
            'plot_number' => 'PLT-TMA-OVERLAP',
            'size_dimension' => '70x100 ft',
            'acreage' => 0.1600,
            'price_pesewas' => 10000000,
            'tenure_type' => 'PRIVATE',
            'min_lat' => 5.7020000, // Inside freshParcel 5.7000000 - 5.7050000
            'max_lat' => 5.7070000,
            'min_lng' => -0.0080000, // Inside freshParcel -0.0100000 - -0.0050000
            'max_lng' => -0.0030000,
        ]);

        $conflict = $overlapParcel->checkSpatialOverlap();
        expect($conflict)->not->toBeNull()
            ->and($conflict?->id)->toBe($freshParcel->id);
    });

    test('RealEstateSeeder executes idempotently and populates master enclaves, plots, allocations, and listings', function (): void {
        [$user, $tenant] = createRealEstateTestContext();

        $seeder = new App\Modules\RealEstate\Database\Seeders\RealEstateSeeder();
        $seeder->run();

        expect(RealEstateProject::query()->where('code', 'ELH-PRIME')->exists())->toBeTrue()
            ->and(RealEstateProject::query()->where('code', 'PRAM-COAST')->exists())->toBeTrue()
            ->and(LandParcel::query()->where('plot_number', 'ELH-PLT-101')->exists())->toBeTrue()
            ->and(PropertyListing::query()->where('slug', 'prime-70x100-residential-plot-east-legon-hills')->exists())->toBeTrue()
            ->and(ClientPlotAllocation::query()->where('allocation_number', 'ALC-ELH-001')->exists())->toBeTrue();

        // Run second time to verify idempotency
        $seeder->run();
        expect(RealEstateProject::query()->where('code', 'ELH-PRIME')->count())->toBe(1);
    });
});
