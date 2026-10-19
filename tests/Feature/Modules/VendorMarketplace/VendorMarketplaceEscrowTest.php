<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\VendorMarketplace\Models\CommissionRateCard;
use App\Modules\VendorMarketplace\Models\EscrowAccount;
use App\Modules\VendorMarketplace\Models\EscrowDispute;
use App\Modules\VendorMarketplace\Models\EscrowLedgerEntry;
use App\Modules\VendorMarketplace\Models\ProformaInvoice;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Modules\VendorMarketplace\Models\VendorProfile;
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
function createVendorMarketplaceTestContext(): array
{
    $user = User::factory()->create([
        'first_name' => 'Escrow',
        'last_name' => 'Governor',
    ]);
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'vendors.view',
        'vendors.manage',
        'vendors.portal.access',
        'escrow.manage',
        'escrow.disputes.resolve',
        'commissions.configure',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'category' => 'vendor-marketplace',
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    app(ModuleManager::class)->enableForTenant('vendor-marketplace', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/VendorMarketplace/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Phase 19: Cross-Cutting Vendor & Service Provider Marketplace, 3-Way Commission Routing & Escrow Ledger', function (): void {
    test('enforces zero invented fees when unconfigured, calculates 3-way split from approved rate card, and executes idempotent escrow funding and release', function (): void {
        [$user, $tenant] = createVendorMarketplaceTestContext();

        // 1. Verify zero-invented-fee invariant when no CommissionRateCard exists
        $unconfiguredSplit = CommissionRateCard::computeSplitForCategory('surveying', 100000);
        expect($unconfiguredSplit['tenant_commission_bps'])->toBe(0)
            ->and($unconfiguredSplit['platform_commission_bps'])->toBe(0)
            ->and($unconfiguredSplit['tenant_commission_pesewas'])->toBe(0)
            ->and($unconfiguredSplit['platform_commission_pesewas'])->toBe(0)
            ->and($unconfiguredSplit['net_vendor_payout_pesewas'])->toBe(100000);

        // 2. Configure explicit approved CommissionRateCard for 'surveying' (7.50% Tenant, 2.50% Platform)
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.rate-cards.upsert'), [
                'category' => 'surveying',
                'tenant_commission_percent' => 7.50,
                'platform_commission_percent' => 2.50,
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $rateCard = CommissionRateCard::query()->where('category', 'surveying')->firstOrFail();
        expect($rateCard->tenant_commission_bps)->toBe(750)
            ->and($rateCard->platform_commission_bps)->toBe(250)
            ->and($rateCard->approved_by_id)->toBe((string) $user->uuid);

        // 3. Onboard Licensed Geomatic Surveyor Vendor
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.vendors.store'), [
                'business_name' => 'Apex Geomatics & Cadastral Survey Ltd',
                'category' => 'surveying',
                'license_number' => 'GhIS-SURV-2026-884',
                'phone' => '+233244889900',
                'email' => 'surveys@apexgeomatics.gh',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $vendor = VendorProfile::query()->where('business_name', 'Apex Geomatics & Cadastral Survey Ltd')->firstOrFail();

        // 4. Dispatch Service Job
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.jobs.store'), [
                'title' => 'Cadastral Site Plan & Beacon Pillar Installation - Plot ELH-04',
                'category' => 'surveying',
                'source_module' => 'real_estate',
                'vendor_profile_id' => $vendor->id,
                'location' => 'East Legon Hills Enclave',
                'description' => 'Prepare licensed surveyor site plan and plant 4 boundary pillars.',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $job = ServiceJob::query()->where('vendor_profile_id', $vendor->id)->firstOrFail();
        expect($job->status)->toBe(ServiceJob::STATUS_ASSIGNED);

        // 5. Submit Proforma Invoice for GHS 10,000.00 (1,000,000 pesewas)
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.proformas.store', $job), [
                'vendor_profile_id' => $vendor->id,
                'gross_amount_ghs' => 10000.00,
                'notes' => 'Includes Lands Commission regional coordinate plotting.',
                'valid_until' => '2026-12-31',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $proforma = ProformaInvoice::query()->where('service_job_id', $job->id)->firstOrFail();
        expect($proforma->gross_amount_pesewas)->toBe(1000000)
            ->and($proforma->tenant_commission_pesewas)->toBe(75000)     // GHS 750.00 (7.5%)
            ->and($proforma->platform_commission_pesewas)->toBe(25000)   // GHS 250.00 (2.5%)
            ->and($proforma->net_vendor_payout_pesewas)->toBe(900000);   // GHS 9,000.00 (90.0%)

        // 6. Fund Escrow (and replay same idempotency_key to verify zero double-credit)
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.fund', $proforma), [
                'payment_reference' => 'OFFLINE-ESCROW-002',
                'idempotency_key' => 'fund-idemp-key-001',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        // Replay exact same idempotency key
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.fund', $proforma), [
                'payment_reference' => 'OFFLINE-ESCROW-002',
                'idempotency_key' => 'fund-idemp-key-001',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow = EscrowAccount::query()->where('proforma_invoice_id', $proforma->id)->firstOrFail();
        expect($escrow->status)->toBe(EscrowAccount::STATUS_FUNDED)
            ->and($escrow->funded_amount_pesewas)->toBe(1000000)
            ->and($escrow->held_balance_pesewas)->toBe(1000000)
            ->and(EscrowLedgerEntry::query()->where('escrow_account_id', $escrow->id)->count())->toBe(1);

        // 7. Release Escrow via 3-way split
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.release', $escrow), [
                'idempotency_key' => 'release-idemp-key-001',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow->refresh();
        $job->refresh();

        expect($escrow->status)->toBe(EscrowAccount::STATUS_RELEASED)
            ->and($escrow->held_balance_pesewas)->toBe(0)
            ->and($escrow->released_vendor_pesewas)->toBe(900000)
            ->and($escrow->released_tenant_commission_pesewas)->toBe(75000)
            ->and($escrow->released_platform_commission_pesewas)->toBe(25000)
            ->and($job->status)->toBe(ServiceJob::STATUS_COMPLETED)
            ->and(EscrowLedgerEntry::query()->where('escrow_account_id', $escrow->id)->count())->toBe(4);
    });

    test('freezes escrow on dispute, blocks release while frozen, and supports buyer refund resolution', function (): void {
        [$user, $tenant] = createVendorMarketplaceTestContext();

        $vendor = VendorProfile::create([
            'user_id' => (string) $user->uuid,
            'business_name' => 'Volta Plumbing Artisans',
            'code' => 'VND-PLUMB1',
            'category' => 'plumbing',
            'status' => 'verified',
        ]);

        $job = ServiceJob::create([
            'job_number' => 'JOB-PLUMB1',
            'vendor_profile_id' => $vendor->id,
            'requester_id' => (string) $user->uuid,
            'source_module' => 'facilities',
            'category' => 'plumbing',
            'title' => 'Burst Main Riser Pipe Repair',
            'status' => ServiceJob::STATUS_QUOTED,
        ]);

        $proforma = ProformaInvoice::create([
            'invoice_number' => 'PFI-PLUMB1',
            'service_job_id' => $job->id,
            'vendor_profile_id' => $vendor->id,
            'client_id' => (string) $user->uuid,
            'currency' => 'GHS',
            'gross_amount_pesewas' => 250000,
            'tenant_commission_bps' => 500,
            'platform_commission_bps' => 200,
            'tenant_commission_pesewas' => 12500,
            'platform_commission_pesewas' => 5000,
            'net_vendor_payout_pesewas' => 232500,
            'status' => ProformaInvoice::STATUS_SUBMITTED,
        ]);

        // Fund Escrow
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.fund', $proforma), [
                'payment_reference' => 'OFFLINE-ESCROW-DISPUTE',
            ]);

        $escrow = EscrowAccount::query()->where('proforma_invoice_id', $proforma->id)->firstOrFail();

        // Open Dispute -> Freezes Escrow
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.disputes.open', $escrow), [
                'reason' => 'Pipe joint still leaking after artisan left the premises.',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow->refresh();
        expect($escrow->status)->toBe(EscrowAccount::STATUS_FROZEN_DISPUTED);

        // Adversarial check: Attempting to release frozen escrow MUST be rejected
        $releaseAttempt = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.release', $escrow));

        $releaseAttempt->assertSessionHasErrors('escrow');
        expect($escrow->fresh()?->held_balance_pesewas)->toBe(250000);

        // Resolve dispute in buyer's favor (full refund)
        $dispute = EscrowDispute::query()->where('escrow_account_id', $escrow->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.disputes.resolve', $dispute), [
                'resolution_type' => EscrowDispute::RESOLUTION_REFUND_TO_BUYER,
                'resolution_notes' => 'Independent facility inspector confirmed defective joint; full buyer refund issued.',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow->refresh();
        $dispute->refresh();

        expect($escrow->status)->toBe(EscrowAccount::STATUS_REFUNDED)
            ->and($escrow->held_balance_pesewas)->toBe(0)
            ->and($escrow->refunded_buyer_pesewas)->toBe(250000)
            ->and($dispute->status)->toBe(EscrowDispute::STATUS_RESOLVED);
    });

    test('adversarial negative tests enforce RBAC and cross-tenant isolation on vendor marketplace', function (): void {
        [$userA, $tenantA] = createVendorMarketplaceTestContext();

        $otherTenant = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $foreignVendor = VendorProfile::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'business_name' => 'Foreign Tenant Artisan',
            'code' => 'VND-FRGN',
            'category' => 'electrical',
            'status' => 'verified',
        ]);

        // Cross-tenant vendor_profile_id on job creation is rejected (422)
        $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('vendor-marketplace.jobs.store'), [
                'title' => 'Attempted Cross-Tenant Job Dispatch',
                'category' => 'electrical',
                'source_module' => 'facilities',
                'vendor_profile_id' => $foreignVendor->id,
            ])
            ->assertSessionHasErrors('vendor_profile_id');

        // Unpermitted user receives 403
        $unpermitted = User::factory()->create(['tenant_id' => $tenantA->id]);
        $unpermitted->tenants()->syncWithoutDetaching([$tenantA->id]);

        $this->actingAs($unpermitted)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->get(route('vendor-marketplace.index'))
            ->assertForbidden();
    });

    test('supports online gateway checkout for escrow funding and pending_payout settlement lifecycle', function (): void {
        [$user, $tenant] = createVendorMarketplaceTestContext();

        // Setup vendor and job
        $vendor = VendorProfile::create([
            'user_id' => (string) $user->uuid,
            'business_name' => 'Acme Electricals Ltd',
            'code' => 'VND-ELEC-01',
            'category' => 'electrical',
            'status' => 'verified',
        ]);

        $job = ServiceJob::create([
            'job_number' => 'JOB-ELEC-01',
            'requester_id' => (string) $user->uuid,
            'source_module' => 'facilities',
            'category' => 'electrical',
            'title' => 'Main Switchboard Rewiring',
            'vendor_profile_id' => $vendor->id,
            'status' => ServiceJob::STATUS_ASSIGNED,
        ]);

        $proforma = ProformaInvoice::create([
            'invoice_number' => 'PFI-ELEC-01',
            'service_job_id' => $job->id,
            'vendor_profile_id' => $vendor->id,
            'client_id' => (string) $user->uuid,
            'currency' => 'GHS',
            'gross_amount_pesewas' => 500000, // 5,000 GHS
            'tenant_commission_bps' => 500,
            'platform_commission_bps' => 200,
            'tenant_commission_pesewas' => 25000,
            'platform_commission_pesewas' => 10000,
            'net_vendor_payout_pesewas' => 465000,
            'status' => ProformaInvoice::STATUS_SUBMITTED,
        ]);

        // 1. Online funding via PaymentGateway checkout session
        $mockGateway = Mockery::mock(App\Contracts\PaymentGateway::class);
        $mockGateway->shouldReceive('createOneTimeCheckoutSession')
            ->once()
            ->andReturn('https://checkout.stripe.test/pay/session_escrow_001');
        app()->instance(App\Contracts\PaymentGateway::class, $mockGateway);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.fund', $proforma), [
                'pay_online' => true,
            ])
            ->assertRedirect('https://checkout.stripe.test/pay/session_escrow_001');

        // 2. Offline/confirmed funding
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.fund', $proforma), [
                'payment_reference' => 'OFFLINE-ESCROW-001',
                'idempotency_key' => 'fund-escrow-payout-test',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow = EscrowAccount::where('proforma_invoice_id', $proforma->id)->firstOrFail();
        expect($escrow->status)->toBe(EscrowAccount::STATUS_FUNDED);

        // 3. Release escrow with hold_payout_for_disbursement = true -> transitions to pending_payout
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.release', $escrow), [
                'idempotency_key' => 'release-escrow-hold-payout',
                'hold_payout_for_disbursement' => true,
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow->refresh();
        expect($escrow->status)->toBe(EscrowAccount::STATUS_PENDING_PAYOUT)
            ->and($escrow->released_vendor_pesewas)->toBe(465000)
            ->and($escrow->held_balance_pesewas)->toBe(0);

        // 4. Finance settles payout
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('vendor-marketplace.escrows.settle-payout', $escrow), [
                'payout_reference' => 'MOMO-PAYOUT-REF-8841',
            ])
            ->assertRedirect(route('vendor-marketplace.index'));

        $escrow->refresh();
        expect($escrow->status)->toBe(EscrowAccount::STATUS_RELEASED)
            ->and(EscrowLedgerEntry::where('entry_type', 'vendor_payout_disbursed')->count())->toBe(1);
    });
});
