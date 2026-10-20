<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use App\Modules\RealEstate\Models\InstallmentSchedule;
use App\Modules\RealEstate\Models\LandParcel;
use App\Modules\RealEstate\Models\RealEstateProject;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\PlatformCreditPurchase;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Models\StoreProduct;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\StoreInventoryService;
use App\Modules\VendorMarketplace\Models\EscrowAccount;
use App\Modules\VendorMarketplace\Models\ProformaInvoice;
use App\Modules\VendorMarketplace\Models\ServiceJob;
use App\Modules\VendorMarketplace\Models\VendorProfile;
use App\Services\Billing\PaymentFulfillmentService;
use App\Services\ModuleManager;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->user);

    $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-b-'.uniqid()]);

    $allPermissions = [
        'realestate.view',
        'realestate.manage',
        'realestate.client.portal',
        'store.view',
        'store.manage',
        'store.platform.purchase',
        'store.orders.manage',
        'vendors.view',
        'vendors.manage',
        'escrow.manage',
        'core.dashboard.access',
    ];

    foreach ($allPermissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'core']);
    }

    setPermissionsTeamId($this->tenant->id);
    $this->user->givePermissionTo($allPermissions);

    app(ModuleManager::class)->enableForTenant('real-estate', $this->tenant);
    app(ModuleManager::class)->enableForTenant('store', $this->tenant);
    app(ModuleManager::class)->enableForTenant('vendor-marketplace', $this->tenant);

    app(App\Services\Tenancy\TenantDatabaseManager::class)->configure($this->tenant);
});

/*
|--------------------------------------------------------------------------
| Real Estate Payment Integrity Tests
|--------------------------------------------------------------------------
*/

test('real estate portal client supplying made-up payment_reference cannot mark installment paid and is forced to gateway checkout', function (): void {
    $client = User::factory()->create(['email' => 'client@estate.test']);
    $client->tenants()->attach($this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    $client->givePermissionTo('realestate.client.portal');

    $project = RealEstateProject::create([
        'name' => 'Airport Residential Enclave',
        'code' => 'ARE-01',
        'location' => 'Greater Accra',
        'region' => 'Greater Accra',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $parcel = LandParcel::create([
        'real_estate_project_id' => $project->id,
        'plot_number' => 'PLOT-ARE-01',
        'size_dimension' => '100x100ft',
        'acreage' => 0.25,
        'price_pesewas' => 2000000, // GHS 20,000
        'tenure_type' => 'PRIVATE',
        'status' => 'available',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $allocation = ClientPlotAllocation::create([
        'tenant_id' => (string) $this->tenant->id,
        'land_parcel_id' => $parcel->id,
        'client_user_id' => (string) $client->uuid,
        'allocation_number' => 'ALLOC-ARE-001',
        'total_price_pesewas' => 2000000,
        'amount_paid_pesewas' => 0,
        'status' => 'active',
        'allocated_at' => now(),
    ]);

    $installment = InstallmentSchedule::create([
        'tenant_id' => (string) $this->tenant->id,
        'client_plot_allocation_id' => $allocation->id,
        'installment_number' => 1,
        'due_date' => now()->addDays(30),
        'amount_due_pesewas' => 1000000, // GHS 10,000
        'amount_paid_pesewas' => 0,
        'status' => 'pending',
    ]);

    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('createOneTimeCheckoutSession')
        ->once()
        ->withArgs(function ($email, $amount, $currency, $redirectUrl, $metadata) use ($installment, $allocation, $client) {
            expect($email)->toBe('client@estate.test')
                ->and($amount)->toBe(1000000)
                ->and($currency)->toBe('GHS')
                ->and($metadata['type'])->toBe('real_estate_installment')
                ->and($metadata['installment_id'])->toBe($installment->id)
                ->and($metadata['allocation_id'])->toBe($allocation->id)
                ->and($metadata['user_uuid'])->toBe((string) $client->uuid);

            return true;
        })
        ->andReturn('https://checkout.gateway.test/pay/session_realestate_001');

    app()->instance(PaymentGateway::class, $mockGateway);

    // Adversarial: Client attempts to supply a fake payment_reference to mark installment paid
    $response = $this->actingAs($client)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('real-estate.installments.pay', $installment), [
            'amount_ghs' => 10000.00,
            'payment_reference' => 'PAY-FAKEREF-HACK-999',
        ]);

    // Must redirect to gateway checkout session, NOT complete the payment
    $response->assertRedirect('https://checkout.gateway.test/pay/session_realestate_001');

    // Assert installment STAYS UNPAID in the database with 0 amount paid
    $installment->refresh();
    $allocation->refresh();
    expect($installment->status)->toBe('pending')
        ->and($installment->amount_paid_pesewas)->toBe(0)
        ->and($installment->payment_reference)->toBeNull()
        ->and($allocation->amount_paid_pesewas)->toBe(0);
});

test('real estate staff offline payment requires mandatory payment_reference and generates audit activity log', function (): void {
    $staff = User::factory()->create();
    $staff->tenants()->attach($this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    $staff->givePermissionTo('realestate.manage');

    $project = RealEstateProject::create([
        'name' => 'Cantonments Heights',
        'code' => 'CH-01',
        'location' => 'Greater Accra',
        'region' => 'Greater Accra',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $parcel = LandParcel::create([
        'real_estate_project_id' => $project->id,
        'plot_number' => 'PLOT-CH-01',
        'size_dimension' => '100x100ft',
        'acreage' => 0.25,
        'price_pesewas' => 1500000,
        'tenure_type' => 'PRIVATE',
        'status' => 'available',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $allocation = ClientPlotAllocation::create([
        'tenant_id' => (string) $this->tenant->id,
        'land_parcel_id' => $parcel->id,
        'client_user_id' => (string) Str::uuid(),
        'allocation_number' => 'ALLOC-CH-001',
        'total_price_pesewas' => 1500000,
        'amount_paid_pesewas' => 0,
        'status' => 'active',
        'allocated_at' => now(),
    ]);

    $installment = InstallmentSchedule::create([
        'tenant_id' => (string) $this->tenant->id,
        'client_plot_allocation_id' => $allocation->id,
        'installment_number' => 1,
        'amount_due_pesewas' => 1500000,
        'amount_paid_pesewas' => 0,
        'due_date' => now()->addDays(30),
        'status' => 'pending',
    ]);

    // 1. Adversarial: Staff submits offline payment without payment_reference -> rejected
    $failResponse = $this->actingAs($staff)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('real-estate.installments.pay', $installment), [
            'amount_ghs' => 15000.00,
            // payment_reference missing
        ]);

    $failResponse->assertSessionHasErrors('payment_reference');
    expect($installment->fresh()->status)->toBe('pending');

    // 2. Legitimate staff offline payment with reference
    $successResponse = $this->actingAs($staff)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('real-estate.installments.pay', $installment), [
            'amount_ghs' => 15000.00,
            'payment_reference' => 'BNK-DEP-2026-CH01',
        ]);

    $successResponse->assertRedirect()
        ->assertSessionHas('status', 'success');

    $installment->refresh();
    $allocation->refresh();
    expect($installment->status)->toBe('paid')
        ->and($installment->amount_paid_pesewas)->toBe(1500000)
        ->and($installment->payment_reference)->toBe('BNK-DEP-2026-CH01')
        ->and($allocation->amount_paid_pesewas)->toBe(1500000)
        ->and($allocation->status)->toBe('completed')
        ->and($parcel->fresh()->status)->toBe('sold');

    // Audit activity log must exist
    $activity = Activity::where('log_name', 'real_estate')
        ->where('subject_type', InstallmentSchedule::class)
        ->where('subject_id', $installment->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['payment_reference'])->toBe('BNK-DEP-2026-CH01')
        ->and($activity->causer_id)->toBe($staff->id);
});

test('fulfillRealEstateInstallment is strictly idempotent and rejects underpayment and cross-tenant fulfillment', function (): void {
    $service = app(PaymentFulfillmentService::class);

    $project = RealEstateProject::create([
        'name' => 'East Legon Hills',
        'code' => 'ELH-01',
        'location' => 'Greater Accra',
        'region' => 'Greater Accra',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $parcel = LandParcel::create([
        'real_estate_project_id' => $project->id,
        'plot_number' => 'PLOT-ELH-01',
        'size_dimension' => '100x100ft',
        'acreage' => 0.25,
        'price_pesewas' => 5000000,
        'tenure_type' => 'PRIVATE',
        'status' => 'available',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $allocation = ClientPlotAllocation::create([
        'tenant_id' => (string) $this->tenant->id,
        'land_parcel_id' => $parcel->id,
        'client_user_id' => (string) Str::uuid(),
        'allocation_number' => 'ALLOC-ELH-001',
        'total_price_pesewas' => 5000000,
        'amount_paid_pesewas' => 0,
        'status' => 'active',
        'allocated_at' => now(),
    ]);

    $installment = InstallmentSchedule::create([
        'tenant_id' => (string) $this->tenant->id,
        'client_plot_allocation_id' => $allocation->id,
        'installment_number' => 1,
        'amount_due_pesewas' => 2500000, // GHS 25,000
        'amount_paid_pesewas' => 0,
        'due_date' => now()->addDays(30),
        'status' => 'pending',
    ]);

    $metadata = [
        'type' => 'real_estate_installment',
        'installment_id' => $installment->id,
        'allocation_id' => $allocation->id,
        'tenant_id' => (string) $this->tenant->id,
    ];

    // 1. Adversarial: Underpayment rejected
    expect(fn () => $service->fulfillRealEstateInstallment($metadata, 2000000, 'tx_underpaid_01'))
        ->toThrow(DomainException::class, 'Underpayment detected');

    expect($installment->fresh()->amount_paid_pesewas)->toBe(0);

    // 2. Adversarial: Cross-tenant fulfillment rejected
    $spoofedMetadata = [
        'type' => 'real_estate_installment',
        'installment_id' => $installment->id,
        'allocation_id' => $allocation->id,
        'tenant_id' => (string) $this->tenantB->id, // Mismatched tenant
    ];
    expect(fn () => $service->fulfillRealEstateInstallment($spoofedMetadata, 2500000, 'tx_crosstenant_01'))
        ->toThrow(DomainException::class, 'Tenant mismatch');

    expect($installment->fresh()->amount_paid_pesewas)->toBe(0);

    // 3. Legitimate gateway fulfillment
    $service->fulfillRealEstateInstallment($metadata, 2500000, 'tx_gw_conf_01');

    $installment->refresh();
    $allocation->refresh();
    expect($installment->status)->toBe('paid')
        ->and($installment->amount_paid_pesewas)->toBe(2500000)
        ->and($installment->payment_reference)->toBe('tx_gw_conf_01')
        ->and($allocation->amount_paid_pesewas)->toBe(2500000);

    // 4. Replay exact same fulfillment -> No double counting!
    $service->fulfillRealEstateInstallment($metadata, 2500000, 'tx_gw_conf_01');

    $installment->refresh();
    $allocation->refresh();
    expect($installment->amount_paid_pesewas)->toBe(2500000)
        ->and($allocation->amount_paid_pesewas)->toBe(2500000);
});

/*
|--------------------------------------------------------------------------
| Store Orders & Platform Credits Payment Integrity Tests
|--------------------------------------------------------------------------
*/

test('store checkout creates order with pending status and rejects client marking it paid via reference', function (): void {
    $product = StoreProduct::create([
        'tenant_id' => (string) $this->tenant->id,
        'name' => 'Custom Hard Hat',
        'slug' => 'custom-hard-hat',
        'price_cents' => 15000, // GHS 150.00
        'stock_quantity' => 10,
        'audience' => 'public',
        'is_active' => true,
    ]);

    // Client attempts to pass a fake payment_reference in checkout
    $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('store.checkout'), [
            'customer_name' => 'Akosua Mensah',
            'customer_email' => 'akosua@store.test',
            'payment_reference' => 'CLIENT-REF-BYPASS-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertRedirect();

    $order = StoreOrder::where('tenant_id', $this->tenant->id)->firstOrFail();

    // Order MUST BE PENDING and payment_reference must be null
    expect($order->status)->toBe('pending')
        ->and($order->payment_reference)->toBeNull()
        ->and($order->total_amount_cents)->toBe(30000);
});

test('store staff offline receipting strictly requires payment_reference and generates audit log', function (): void {
    $staff = User::factory()->create();
    $staff->tenants()->attach($this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    $staff->givePermissionTo('store.orders.manage');

    $order = StoreOrder::create([
        'tenant_id' => (string) $this->tenant->id,
        'order_number' => 'ORD-TEST-900',
        'customer_name' => 'Yaw Frimpong',
        'customer_email' => 'yaw@store.test',
        'total_amount_cents' => 45000,
        'currency' => 'GHS',
        'status' => 'pending',
    ]);

    // 1. Staff confirms payment without payment_reference -> rejected
    $failResponse = $this->actingAs($staff)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('store.orders.confirm-payment', $order), [
            // no payment_reference
        ]);
    $failResponse->assertSessionHasErrors('payment_reference');
    expect($order->fresh()->status)->toBe('pending');

    // 2. Staff confirms payment with audited reference
    $this->actingAs($staff)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('store.orders.confirm-payment', $order), [
            'payment_reference' => 'STORE-OFFLINE-REC-001',
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->payment_reference)->toBe('STORE-OFFLINE-REC-001');

    $activity = Activity::where('log_name', 'store')
        ->where('subject_type', StoreOrder::class)
        ->where('subject_id', $order->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['payment_reference'])->toBe('STORE-OFFLINE-REC-001')
        ->and($activity->causer_id)->toBe($staff->id);
});

test('fulfillStoreOrder is strictly idempotent and rejects underpayment and cross-tenant mismatch', function (): void {
    $service = app(PaymentFulfillmentService::class);

    $order = StoreOrder::create([
        'tenant_id' => (string) $this->tenant->id,
        'order_number' => 'ORD-FULFILL-01',
        'customer_name' => 'Kojo Manu',
        'customer_email' => 'kojo@store.test',
        'total_amount_cents' => 20000, // GHS 200.00
        'currency' => 'GHS',
        'status' => 'pending',
    ]);

    $metadata = [
        'type' => 'store_order',
        'order_id' => $order->id,
        'tenant_id' => (string) $this->tenant->id,
    ];

    // Underpayment rejected
    expect(fn () => $service->fulfillStoreOrder($metadata, 15000, 'tx_order_underpaid'))
        ->toThrow(DomainException::class, 'Underpayment detected');

    // Cross-tenant rejected
    expect(fn () => $service->fulfillStoreOrder([
        'type' => 'store_order',
        'order_id' => $order->id,
        'tenant_id' => (string) $this->tenantB->id,
    ], 20000, 'tx_order_crosstenant'))
        ->toThrow(DomainException::class, 'Tenant mismatch');

    // Successful fulfillment
    $service->fulfillStoreOrder($metadata, 20000, 'tx_order_gw_001');
    expect($order->fresh()->status)->toBe('paid')
        ->and($order->fresh()->payment_reference)->toBe('tx_order_gw_001');

    // Replay idempotency
    $service->fulfillStoreOrder($metadata, 20000, 'tx_order_gw_001');
    expect($order->fresh()->status)->toBe('paid');
});

test('store purchasePlatformCredits creates pending purchase, redirects to gateway, and credits wallet only on fulfillment', function (): void {
    $this->tenant->update(['country' => 'Ghana', 'billing_currency' => null]);
    $package = PlatformCreditPackage::create([
        'name' => 'SMS 10,000 Quota',
        'credit_type' => 'sms',
        'amount_credits' => 10000,
        'price_cents' => 40000, // GHS 400.00
        'currency' => 'GHS',
        'is_active' => true,
    ]);

    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('createOneTimeCheckoutSession')
        ->once()
        ->andReturn('https://checkout.gateway.test/pay/credits_10k');
    app()->instance(PaymentGateway::class, $mockGateway);

    $response = $this->actingAs($this->user)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('store.platform.purchase'), [
            'package_id' => $package->id,
        ]);

    $response->assertRedirect('https://checkout.gateway.test/pay/credits_10k');

    // Pending purchase must exist with status 'pending'
    $purchase = PlatformCreditPurchase::where('tenant_id', $this->tenant->id)
        ->where('package_id', $package->id)
        ->firstOrFail();

    expect($purchase->status)->toBe('pending');

    // Wallet balance MUST STILL BE ZERO until confirmed fulfillment
    $wallet = TenantCreditWallet::where('tenant_id', $this->tenant->id)
        ->where('credit_type', 'sms')
        ->first();
    expect($wallet?->balance ?? 0)->toBe(0);

    // Now gateway fulfills payment
    $service = app(PaymentFulfillmentService::class);
    $service->fulfillPlatformCreditPurchase([
        'type' => 'platform_credit_purchase',
        'package_id' => $package->id,
        'purchase_id' => $purchase->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 40000, 'tx_credits_gw_999');

    // Purchase is now succeeded and wallet credited exactly once
    $purchase->refresh();
    $wallet = TenantCreditWallet::where('tenant_id', $this->tenant->id)
        ->where('credit_type', 'sms')
        ->firstOrFail();

    expect($purchase->status)->toBe('succeeded')
        ->and($purchase->payment_reference)->toBe('tx_credits_gw_999')
        ->and($wallet->balance)->toBe(10000)
        ->and($wallet->total_purchased)->toBe(10000);

    // Replay idempotency: identical transaction ID does not double-credit
    $service->fulfillPlatformCreditPurchase([
        'type' => 'platform_credit_purchase',
        'package_id' => $package->id,
        'purchase_id' => $purchase->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 40000, 'tx_credits_gw_999');

    $wallet->refresh();
    expect($wallet->balance)->toBe(10000)
        ->and($wallet->total_purchased)->toBe(10000);
});

test('fulfillPlatformCreditPurchase rejects underpayment and cross-tenant mismatch', function (): void {
    $service = app(PaymentFulfillmentService::class);

    $package = PlatformCreditPackage::create([
        'name' => 'AI Token Pack 500k',
        'credit_type' => 'ai_tokens',
        'amount_credits' => 500000,
        'price_cents' => 100000,
        'currency' => 'GHS',
        'is_active' => true,
    ]);

    // Underpayment rejected
    expect(fn () => $service->fulfillPlatformCreditPurchase([
        'package_id' => $package->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 50000, 'tx_credits_underpaid'))
        ->toThrow(DomainException::class, 'Underpayment detected');

    // Cross-tenant purchase ID rejected
    $purchaseInTenantA = PlatformCreditPurchase::create([
        'tenant_id' => (string) $this->tenant->id,
        'package_id' => $package->id,
        'payment_reference' => 'PENDING-TENANT-A',
        'amount_paid_cents' => 100000,
        'credits_awarded' => 500000,
        'status' => 'pending',
    ]);

    expect(fn () => $service->fulfillPlatformCreditPurchase([
        'package_id' => $package->id,
        'purchase_id' => $purchaseInTenantA->id,
        'tenant_id' => (string) $this->tenantB->id,
    ], 100000, 'tx_crosstenant_credits'))
        ->toThrow(DomainException::class, 'Tenant mismatch');
});

/*
|--------------------------------------------------------------------------
| Escrow Funding Payment Integrity Tests
|--------------------------------------------------------------------------
*/

test('escrow fundEscrow non-online path requires mandatory payment_reference and generates audit activity log', function (): void {
    $staff = User::factory()->create();
    $staff->tenants()->attach($this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    $staff->givePermissionTo('escrow.manage');

    $vendorUser = User::factory()->create();
    $vendor = VendorProfile::create([
        'tenant_id' => (string) $this->tenant->id,
        'user_id' => (string) $vendorUser->uuid,
        'business_name' => 'Accra Plumbing Co.',
        'code' => 'VND-ACC-01',
        'category' => 'plumbing',
        'status' => 'approved',
    ]);

    $job = ServiceJob::create([
        'tenant_id' => (string) $this->tenant->id,
        'job_number' => 'JOB-ESC-TEST',
        'vendor_profile_id' => $vendor->id,
        'requester_id' => (string) $this->user->uuid,
        'source_module' => 'facilities',
        'category' => 'plumbing',
        'title' => 'Fix Main Water Reservoir',
        'status' => ServiceJob::STATUS_QUOTED,
    ]);

    $proforma = ProformaInvoice::create([
        'tenant_id' => (string) $this->tenant->id,
        'invoice_number' => 'PFI-ESC-01',
        'service_job_id' => $job->id,
        'vendor_profile_id' => $vendor->id,
        'client_id' => (string) $this->user->uuid,
        'currency' => 'GHS',
        'gross_amount_pesewas' => 500000, // GHS 5,000
        'tenant_commission_bps' => 500,
        'platform_commission_bps' => 200,
        'tenant_commission_pesewas' => 25000,
        'platform_commission_pesewas' => 10000,
        'net_vendor_payout_pesewas' => 465000,
        'status' => ProformaInvoice::STATUS_SUBMITTED,
    ]);

    // 1. Staff attempts offline funding without payment_reference -> rejected
    $failResponse = $this->actingAs($staff)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('vendor-marketplace.escrows.fund', $proforma), [
            // no payment_reference
        ]);
    $failResponse->assertSessionHasErrors('payment_reference');
    expect($proforma->fresh()->status)->toBe(ProformaInvoice::STATUS_SUBMITTED);

    // 2. Staff funds with audited reference
    $this->actingAs($staff)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->post(route('vendor-marketplace.escrows.fund', $proforma), [
            'payment_reference' => 'ESC-OFFLINE-BNK-99',
        ])
        ->assertRedirect(route('vendor-marketplace.index'));

    $proforma->refresh();
    $escrow = EscrowAccount::where('proforma_invoice_id', $proforma->id)->firstOrFail();
    expect($escrow->status)->toBe(EscrowAccount::STATUS_FUNDED)
        ->and($escrow->funded_amount_pesewas)->toBe(500000)
        ->and($proforma->status)->toBe(ProformaInvoice::STATUS_FUNDED)
        ->and($job->fresh()->status)->toBe(ServiceJob::STATUS_IN_ESCROW);

    $activity = Activity::where('log_name', 'vendor_marketplace')
        ->where('subject_type', ProformaInvoice::class)
        ->where('subject_id', $proforma->id)
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['payment_reference'])->toBe('ESC-OFFLINE-BNK-99')
        ->and($activity->causer_id)->toBe($staff->id);
});

test('fulfillEscrowFunding is strictly idempotent and rejects underpayment and cross-tenant mismatch', function (): void {
    $service = app(PaymentFulfillmentService::class);

    $vendorUser = User::factory()->create();
    $vendor = VendorProfile::create([
        'tenant_id' => (string) $this->tenant->id,
        'user_id' => (string) $vendorUser->uuid,
        'business_name' => 'Accra HVAC Ltd.',
        'code' => 'VND-ACC-02',
        'category' => 'electrical',
        'status' => 'approved',
    ]);

    $job = ServiceJob::create([
        'tenant_id' => (string) $this->tenant->id,
        'job_number' => 'JOB-ESC-FULFILL',
        'requester_id' => (string) $this->user->uuid,
        'source_module' => 'facilities',
        'category' => 'electrical',
        'title' => 'HVAC Motor Replacement',
        'status' => ServiceJob::STATUS_QUOTED,
    ]);

    $proforma = ProformaInvoice::create([
        'tenant_id' => (string) $this->tenant->id,
        'invoice_number' => 'PFI-ESC-FULFILL-01',
        'service_job_id' => $job->id,
        'vendor_profile_id' => $vendor->id,
        'client_id' => (string) $this->user->uuid,
        'currency' => 'GHS',
        'gross_amount_pesewas' => 800000, // GHS 8,000
        'tenant_commission_bps' => 500,
        'platform_commission_bps' => 200,
        'tenant_commission_pesewas' => 40000,
        'platform_commission_pesewas' => 16000,
        'net_vendor_payout_pesewas' => 744000,
        'status' => ProformaInvoice::STATUS_SUBMITTED,
    ]);

    $metadata = [
        'type' => 'escrow_funding',
        'proforma_id' => $proforma->id,
        'tenant_id' => (string) $this->tenant->id,
    ];

    // Underpayment rejected
    expect(fn () => $service->fulfillEscrowFunding($metadata, 700000, 'tx_escrow_underpaid'))
        ->toThrow(DomainException::class, 'Underpayment detected');

    // Cross-tenant rejected
    expect(fn () => $service->fulfillEscrowFunding([
        'type' => 'escrow_funding',
        'proforma_id' => $proforma->id,
        'tenant_id' => (string) $this->tenantB->id,
    ], 800000, 'tx_escrow_crosstenant'))
        ->toThrow(DomainException::class, 'Tenant mismatch');

    // Legitimate gateway fulfillment
    $escrow = $service->fulfillEscrowFunding($metadata, 800000, 'tx_escrow_conf_01');
    expect($escrow->status)->toBe(EscrowAccount::STATUS_FUNDED)
        ->and($escrow->funded_amount_pesewas)->toBe(800000)
        ->and($proforma->fresh()->status)->toBe(ProformaInvoice::STATUS_FUNDED)
        ->and($job->fresh()->status)->toBe(ServiceJob::STATUS_IN_ESCROW);

    // Replay idempotency
    $replayedEscrow = $service->fulfillEscrowFunding($metadata, 800000, 'tx_escrow_conf_01');
    expect($replayedEscrow->id)->toBe($escrow->id);
});

/*
|--------------------------------------------------------------------------
| Callback Controller & Merchant Webhook End-to-End Tests
|--------------------------------------------------------------------------
*/

test('callback controller fulfills real estate installment and redirects to client portal', function (): void {
    $client = User::factory()->create();
    $client->tenants()->attach($this->tenant->id);
    setPermissionsTeamId($this->tenant->id);
    $client->givePermissionTo('realestate.client.portal');

    $project = RealEstateProject::create([
        'name' => 'Labone Luxury Court',
        'code' => 'LLC-01',
        'location' => 'Greater Accra',
        'region' => 'Greater Accra',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $parcel = LandParcel::create([
        'real_estate_project_id' => $project->id,
        'plot_number' => 'PLOT-LLC-01',
        'size_dimension' => '80x100ft',
        'acreage' => 0.18,
        'price_pesewas' => 1000000,
        'tenure_type' => 'PRIVATE',
        'status' => 'available',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $allocation = ClientPlotAllocation::create([
        'tenant_id' => (string) $this->tenant->id,
        'land_parcel_id' => $parcel->id,
        'client_user_id' => (string) $client->uuid,
        'allocation_number' => 'ALLOC-LLC-001',
        'total_price_pesewas' => 1000000,
        'amount_paid_pesewas' => 0,
        'status' => 'active',
        'allocated_at' => now(),
    ]);

    $installment = InstallmentSchedule::create([
        'tenant_id' => (string) $this->tenant->id,
        'client_plot_allocation_id' => $allocation->id,
        'installment_number' => 1,
        'amount_due_pesewas' => 1000000,
        'amount_paid_pesewas' => 0,
        'due_date' => now()->addDays(30),
        'status' => 'pending',
    ]);

    $mockGateway = Mockery::mock(PaymentGateway::class);
    $mockGateway->shouldReceive('verifyTransaction')
        ->with('session_cb_estate_01')
        ->andReturn([
            'status' => 'success',
            'amount' => 1000000,
            'transaction_id' => 'tx_estate_cb_01',
            'metadata' => [
                'type' => 'real_estate_installment',
                'installment_id' => $installment->id,
                'allocation_id' => $allocation->id,
                'tenant_id' => (string) $this->tenant->id,
            ],
        ]);
    app()->instance(PaymentGateway::class, $mockGateway);

    $response = $this->actingAs($client)
        ->withSession(['active_tenant_id' => $this->tenant->id])
        ->get(route('billing.callback', ['reference' => 'session_cb_estate_01']));

    $response->assertRedirect(route('real-estate.portal.index'))
        ->assertSessionHas('status', 'success');

    expect($installment->fresh()->status)->toBe('paid')
        ->and($installment->fresh()->payment_reference)->toBe('tx_estate_cb_01');
});

test('merchant webhook rejects cross-tenant spoofing attempts with 403', function (): void {
    TenantPaymentGateway::create([
        'tenant_id' => $this->tenant->id,
        'provider' => 'paystack',
        'api_key_encrypted' => 'sk_test_secret_key',
        'is_active' => true,
    ]);

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_crosstenant_spoof',
            'amount' => 1000000,
            'currency' => 'GHS',
            'customer' => ['email' => 'hacker@test.com'],
            'metadata' => [
                'type' => 'real_estate_installment',
                'installment_id' => 999,
                'tenant_id' => (string) $this->tenantB->id, // Tenant B target sent to Tenant A webhook!
            ],
        ],
    ]);

    $signature = hash_hmac('sha512', (string) $payload, 'sk_test_secret_key');

    $response = $this->call('POST', route('webhooks.merchant.paystack', $this->tenant), [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], (string) $payload);

    $response->assertStatus(403);
});

test('installment rejects second payment with different transaction id when nothing remains due and records for refund', function (): void {
    $service = app(PaymentFulfillmentService::class);

    $project = RealEstateProject::create([
        'name' => 'Concurrent Estate Project',
        'code' => 'CONC-ESTATE',
        'status' => 'active',
        'location' => 'Greater Accra',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $parcel = LandParcel::create([
        'real_estate_project_id' => $project->id,
        'plot_number' => 'PARCEL-CONC-01',
        'size_dimension' => '70x100ft',
        'acreage' => 0.16,
        'price_pesewas' => 1500000,
        'tenure_type' => 'PRIVATE',
        'status' => 'available',
        'tenant_id' => (string) $this->tenant->id,
    ]);

    $allocation = ClientPlotAllocation::create([
        'tenant_id' => (string) $this->tenant->id,
        'land_parcel_id' => $parcel->id,
        'client_user_id' => (string) Str::uuid(),
        'allocation_number' => 'ALLOC-CONC-001',
        'total_price_pesewas' => 1500000,
        'amount_paid_pesewas' => 0,
        'status' => 'active',
        'allocated_at' => now(),
    ]);

    $installment = InstallmentSchedule::create([
        'tenant_id' => (string) $this->tenant->id,
        'client_plot_allocation_id' => $allocation->id,
        'installment_number' => 1,
        'amount_due_pesewas' => 1500000,
        'amount_paid_pesewas' => 0,
        'due_date' => now()->addDays(30),
        'status' => 'pending',
    ]);

    $metadata = [
        'type' => 'real_estate_installment',
        'installment_id' => $installment->id,
        'allocation_id' => $allocation->id,
        'tenant_id' => (string) $this->tenant->id,
    ];

    // 1. Payment 1 completes the installment
    $service->fulfillRealEstateInstallment($metadata, 1500000, 'tx_conc_001', 'paystack', 'GHS');

    expect($installment->fresh()->status)->toBe('paid')
        ->and($installment->fresh()->amount_paid_pesewas)->toBe(1500000)
        ->and($allocation->fresh()->amount_paid_pesewas)->toBe(1500000);

    // 2. Concurrent Payment 2 arrives with a different transaction ID
    expect(fn () => $service->fulfillRealEstateInstallment($metadata, 1500000, 'tx_conc_002', 'paystack', 'GHS'))
        ->toThrow(DomainException::class, 'already fully paid');

    // State invariants: NOT over-credited
    expect($installment->fresh()->amount_paid_pesewas)->toBe(1500000)
        ->and($allocation->fresh()->amount_paid_pesewas)->toBe(1500000);

    // Transaction ledger: refund record exists for tx_conc_002
    $refundTx = Transaction::where('provider_transaction_id', 'tx_conc_002')->first();
    expect($refundTx)->not->toBeNull()
        ->and($refundTx->status)->toBe('refund_pending')
        ->and($refundTx->type)->toBe('real_estate_installment_refund');
});

test('fulfillment handlers reject payments with mismatched currencies', function (): void {
    $service = app(PaymentFulfillmentService::class);

    // 1. Real estate installment: expects GHS, given USD
    $metadata = [
        'type' => 'real_estate_installment',
        'installment_id' => 9999,
        'tenant_id' => (string) $this->tenant->id,
    ];
    expect(fn () => $service->fulfillRealEstateInstallment($metadata, 100000, 'tx_curr_01', 'paystack', 'USD'))
        ->toThrow(DomainException::class, 'Currency mismatch');

    // 2. Escrow funding: proforma currency GHS, given EUR
    $vendor = VendorProfile::create([
        'user_id' => (string) Str::uuid(),
        'tenant_id' => (string) $this->tenant->id,
        'business_name' => 'Currency Plumbing',
        'code' => 'VEND-CURR-01',
        'category' => 'plumbing',
        'status' => 'verified',
    ]);
    $job = ServiceJob::create([
        'tenant_id' => (string) $this->tenant->id,
        'job_number' => 'JOB-CURR-01',
        'requester_id' => (string) $this->user->uuid,
        'source_module' => 'facilities',
        'category' => 'plumbing',
        'title' => 'Currency Test Job',
        'status' => ServiceJob::STATUS_QUOTED,
    ]);
    $proforma = ProformaInvoice::create([
        'tenant_id' => (string) $this->tenant->id,
        'invoice_number' => 'PI-CURR-01',
        'service_job_id' => $job->id,
        'vendor_profile_id' => $vendor->id,
        'client_id' => (string) $this->user->uuid,
        'currency' => 'GHS',
        'gross_amount_pesewas' => 200000,
        'tenant_commission_bps' => 500,
        'platform_commission_bps' => 250,
        'tenant_commission_pesewas' => 10000,
        'platform_commission_pesewas' => 5000,
        'net_vendor_payout_pesewas' => 185000,
        'status' => 'accepted',
    ]);
    $escrowMetadata = [
        'type' => 'escrow_funding',
        'proforma_id' => $proforma->id,
        'tenant_id' => (string) $this->tenant->id,
    ];
    expect(fn () => $service->fulfillEscrowFunding($escrowMetadata, 200000, 'tx_curr_02', 'paystack', 'EUR'))
        ->toThrow(DomainException::class, 'Currency mismatch');

    // 3. Store order: order currency GHS, given USD
    $product = StoreProduct::create([
        'tenant_id' => (string) $this->tenant->id,
        'name' => 'Currency Product',
        'slug' => 'currency-product',
        'sku' => 'CURR-01',
        'price_cents' => 1500,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);
    $inventoryService = app(StoreInventoryService::class);
    $order = $inventoryService->placeOrder(
        (string) $this->tenant->id,
        [
            'customer_name' => 'Currency Buyer',
            'customer_email' => 'buyer@curr.test',
        ],
        [
            ['product_id' => $product->id, 'quantity' => 1],
        ]
    );
    $orderMetadata = [
        'type' => 'store_order',
        'order_id' => $order->id,
        'tenant_id' => (string) $this->tenant->id,
    ];
    expect(fn () => $service->fulfillStoreOrder($orderMetadata, 1500, 'tx_curr_03', 'paystack', 'USD'))
        ->toThrow(DomainException::class, 'Currency mismatch');

    // 4. Platform credit purchase: package currency GHS, given USD
    $package = PlatformCreditPackage::create([
        'name' => 'Currency Credits',
        'credit_type' => 'sms',
        'amount_credits' => 100,
        'price_cents' => 500,
        'currency' => 'GHS',
        'is_active' => true,
    ]);
    $creditMetadata = [
        'type' => 'platform_credit_purchase',
        'package_id' => $package->id,
        'tenant_id' => (string) $this->tenant->id,
    ];
    expect(fn () => $service->fulfillPlatformCreditPurchase($creditMetadata, 500, 'tx_curr_04', 'stripe', 'USD'))
        ->toThrow(DomainException::class, 'Currency mismatch');
});

test('fulfillment handlers create idempotent Transaction ledger rows for all four types', function (): void {
    $service = app(PaymentFulfillmentService::class);

    // 1. Real Estate Installment
    $project = RealEstateProject::create([
        'name' => 'Ledger Project',
        'code' => 'LEDGER-ESTATE',
        'status' => 'active',
        'location' => 'Greater Accra',
        'tenant_id' => (string) $this->tenant->id,
    ]);
    $parcel = LandParcel::create([
        'real_estate_project_id' => $project->id,
        'plot_number' => 'PARCEL-LEDGER-01',
        'size_dimension' => '70x100ft',
        'acreage' => 0.16,
        'price_pesewas' => 500000,
        'tenure_type' => 'PRIVATE',
        'status' => 'available',
        'tenant_id' => (string) $this->tenant->id,
    ]);
    $allocation = ClientPlotAllocation::create([
        'tenant_id' => (string) $this->tenant->id,
        'land_parcel_id' => $parcel->id,
        'client_user_id' => (string) Str::uuid(),
        'allocation_number' => 'ALLOC-LEDGER-001',
        'total_price_pesewas' => 500000,
        'amount_paid_pesewas' => 0,
        'status' => 'active',
        'allocated_at' => now(),
    ]);
    $installment = InstallmentSchedule::create([
        'tenant_id' => (string) $this->tenant->id,
        'client_plot_allocation_id' => $allocation->id,
        'installment_number' => 1,
        'amount_due_pesewas' => 500000,
        'amount_paid_pesewas' => 0,
        'due_date' => now()->addDays(30),
        'status' => 'pending',
    ]);
    $service->fulfillRealEstateInstallment([
        'type' => 'real_estate_installment',
        'installment_id' => $installment->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 500000, 'tx_ledger_estate_01', 'paystack', 'GHS');

    $estateTx = Transaction::where('provider_transaction_id', 'tx_ledger_estate_01')->first();
    expect($estateTx)->not->toBeNull()
        ->and($estateTx->status)->toBe('success')
        ->and($estateTx->type)->toBe('real_estate_installment')
        ->and($estateTx->amount)->toBe(500000);

    // 2. Escrow Funding
    $vendor = VendorProfile::create([
        'user_id' => (string) Str::uuid(),
        'tenant_id' => (string) $this->tenant->id,
        'business_name' => 'Ledger Vendor',
        'code' => 'VEND-LEDGER-01',
        'category' => 'electrical',
        'status' => 'verified',
    ]);
    $job = ServiceJob::create([
        'tenant_id' => (string) $this->tenant->id,
        'job_number' => 'JOB-LEDGER-01',
        'requester_id' => (string) $this->user->uuid,
        'source_module' => 'facilities',
        'category' => 'electrical',
        'title' => 'Ledger Electrical Job',
        'status' => ServiceJob::STATUS_QUOTED,
    ]);
    $proforma = ProformaInvoice::create([
        'tenant_id' => (string) $this->tenant->id,
        'invoice_number' => 'PI-LEDGER-01',
        'service_job_id' => $job->id,
        'vendor_profile_id' => $vendor->id,
        'client_id' => (string) $this->user->uuid,
        'currency' => 'GHS',
        'gross_amount_pesewas' => 300000,
        'tenant_commission_bps' => 500,
        'platform_commission_bps' => 250,
        'tenant_commission_pesewas' => 15000,
        'platform_commission_pesewas' => 7500,
        'net_vendor_payout_pesewas' => 277500,
        'status' => 'accepted',
    ]);
    $service->fulfillEscrowFunding([
        'type' => 'escrow_funding',
        'proforma_id' => $proforma->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 300000, 'tx_ledger_escrow_01', 'paystack', 'GHS');

    $escrowTx = Transaction::where('provider_transaction_id', 'tx_ledger_escrow_01')->first();
    expect($escrowTx)->not->toBeNull()
        ->and($escrowTx->status)->toBe('success')
        ->and($escrowTx->type)->toBe('escrow_funding');

    // 3. Store Order
    $product = StoreProduct::create([
        'tenant_id' => (string) $this->tenant->id,
        'name' => 'Ledger Item',
        'slug' => 'ledger-item',
        'sku' => 'LEDGER-01',
        'price_cents' => 2000,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);
    $inventoryService = app(StoreInventoryService::class);
    $order = $inventoryService->placeOrder(
        (string) $this->tenant->id,
        [
            'customer_name' => 'Ledger Customer',
            'customer_email' => 'ledger@test.com',
        ],
        [
            ['product_id' => $product->id, 'quantity' => 1],
        ]
    );
    $service->fulfillStoreOrder([
        'type' => 'store_order',
        'order_id' => $order->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 2000, 'tx_ledger_order_01', 'paystack', 'GHS');

    $orderTx = Transaction::where('provider_transaction_id', 'tx_ledger_order_01')->first();
    expect($orderTx)->not->toBeNull()
        ->and($orderTx->status)->toBe('success')
        ->and($orderTx->type)->toBe('store_order');

    // 4. Platform Credit Purchase
    $package = PlatformCreditPackage::create([
        'name' => 'Ledger Credits',
        'credit_type' => 'email',
        'amount_credits' => 500,
        'price_cents' => 1000,
        'currency' => 'GHS',
        'is_active' => true,
    ]);
    $service->fulfillPlatformCreditPurchase([
        'type' => 'platform_credit_purchase',
        'package_id' => $package->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 1000, 'tx_ledger_credit_01', 'stripe', 'GHS');

    $creditTx = Transaction::where('provider_transaction_id', 'tx_ledger_credit_01')->first();
    expect($creditTx)->not->toBeNull()
        ->and($creditTx->status)->toBe('success')
        ->and($creditTx->type)->toBe('platform_credit_purchase');

    // 5. Assert idempotency on replay
    $service->fulfillPlatformCreditPurchase([
        'type' => 'platform_credit_purchase',
        'package_id' => $package->id,
        'tenant_id' => (string) $this->tenant->id,
    ], 1000, 'tx_ledger_credit_01', 'stripe', 'GHS');

    expect(Transaction::where('provider_transaction_id', 'tx_ledger_credit_01')->count())->toBe(1);
});
