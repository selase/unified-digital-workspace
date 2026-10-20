<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\PlatformCreditPurchase;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Models\StoreProduct;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\PlatformCreditService;
use App\Services\Billing\PaymentFulfillmentService;
use App\Services\ModuleManager;
use Spatie\Permission\Models\Permission;

function createStoreTestContext(): array
{
    $user = User::factory()->create([
        'email' => 'store-admin-'.uniqid().'@example.com',
    ]);
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana']);

    foreach (['store.view', 'store.manage', 'store.platform.purchase', 'store.orders.manage'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'store']);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo(['store.view', 'store.manage', 'store.platform.purchase', 'store.orders.manage']);
    app(ModuleManager::class)->enableForTenant('store', $tenant);

    app(App\Services\Tenancy\TenantDatabaseManager::class)->configure($tenant);
    Illuminate\Support\Facades\Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/Store/Database/Migrations'),
        '--realpath' => true,
        '--force' => true,
    ]);

    return [$user, $tenant];
}

describe('Phase 25: Two-Tier Store: Platform Quota Store & Per-Tenant Product Store (TRK-MARKETPLACE-STORES)', function (): void {

    test('tier 1: purchases platform SMS and AI credit packages and decrements on delivery verification', function (): void {
        [$user, $tenant] = createStoreTestContext();

        // 1. Create Platform Packages
        $smsPackage = PlatformCreditPackage::create([
            'name' => 'Institutional SMS Quota — 5,000 Units',
            'credit_type' => 'sms',
            'amount_credits' => 5000,
            'price_cents' => 25000, // GHS 250.00
            'currency' => 'GHS',
            'is_active' => true,
        ]);

        $aiPackage = PlatformCreditPackage::create([
            'name' => 'AI Generation Pack — 100,000 Tokens',
            'credit_type' => 'ai_token',
            'amount_credits' => 100000,
            'price_cents' => 50000, // GHS 500.00
            'currency' => 'GHS',
            'is_active' => true,
        ]);

        $mockGateway = Mockery::mock(PaymentGateway::class);
        $mockGateway->shouldReceive('createOneTimeCheckoutSession')
            ->andReturn('https://checkout.paystack.test/pay/pkg_test');
        app()->instance(PaymentGateway::class, $mockGateway);

        // 2. Purchase SMS Package -> Redirects to Gateway checkout and creates pending purchase
        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.platform.purchase'), [
                'package_id' => $smsPackage->id,
            ]);

        $response->assertRedirect('https://checkout.paystack.test/pay/pkg_test');

        $pendingPurchase = PlatformCreditPurchase::where('tenant_id', $tenant->id)->where('package_id', $smsPackage->id)->firstOrFail();
        expect($pendingPurchase->status)->toBe('pending');

        // Confirm fulfillment
        $fulfillmentService = app(PaymentFulfillmentService::class);
        $fulfillmentService->fulfillPlatformCreditPurchase([
            'package_id' => $smsPackage->id,
            'purchase_id' => $pendingPurchase->id,
            'tenant_id' => (string) $tenant->id,
        ], 25000, 'tx_sms_paystack_01', 'paystack');

        $smsWallet = TenantCreditWallet::where('tenant_id', $tenant->id)->where('credit_type', 'sms')->firstOrFail();
        expect($smsWallet->balance)->toBe(5000)
            ->and($smsWallet->total_purchased)->toBe(5000);

        // 3. Purchase AI Package
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.platform.purchase'), [
                'package_id' => $aiPackage->id,
            ])
            ->assertRedirect('https://checkout.paystack.test/pay/pkg_test');

        $pendingAiPurchase = PlatformCreditPurchase::where('tenant_id', $tenant->id)->where('package_id', $aiPackage->id)->firstOrFail();
        expect($pendingAiPurchase->status)->toBe('pending');

        $fulfillmentService->fulfillPlatformCreditPurchase([
            'package_id' => $aiPackage->id,
            'purchase_id' => $pendingAiPurchase->id,
            'tenant_id' => (string) $tenant->id,
        ], 50000, 'tx_ai_paystack_01', 'paystack');

        $aiWallet = TenantCreditWallet::where('tenant_id', $tenant->id)->where('credit_type', 'ai_token')->firstOrFail();
        expect($aiWallet->balance)->toBe(100000);

        // 4. Test Delivery-Based Quota Consumption
        $creditService = app(PlatformCreditService::class);

        // Dispatch 50 SMS messages
        $consumed = $creditService->consumeCredit((string) $tenant->id, 'sms', 50);
        expect($consumed)->toBeTrue();

        $smsWallet->refresh();
        expect($smsWallet->balance)->toBe(4950)
            ->and($smsWallet->total_consumed)->toBe(50);

        // Attempting to consume more credits than balance returns false without overdraw
        $overdrawAttempt = $creditService->consumeCredit((string) $tenant->id, 'sms', 10000);
        expect($overdrawAttempt)->toBeFalse();

        $smsWallet->refresh();
        expect($smsWallet->balance)->toBe(4950); // Unaltered!
    });

    test('tier 2: orders tenant products and atomic inventory lock prevents over-selling', function (): void {
        [$user, $tenant] = createStoreTestContext();

        // 1. Create Catalog Product with exactly 5 units in stock
        $product = StoreProduct::create([
            'tenant_id' => $tenant->id,
            'name' => 'High-Visibility Geodetic Field Vest',
            'slug' => 'high-vis-geodetic-field-vest',
            'sku' => 'VEST-GEO-01',
            'description' => 'ISO certified reflective survey vest.',
            'price_cents' => 15000, // GHS 150.00
            'stock_quantity' => 5, // Exactly 5 units
            'audience' => 'staff_only',
            'is_active' => true,
        ]);

        // 2. Successful Order: Buy 2 units
        $orderResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.checkout'), [
                'customer_name' => 'Ama Serwaa',
                'customer_email' => 'ama.serwaa@example.com',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $orderResponse->assertRedirect()
            ->assertSessionHas('status', 'success');

        $order = StoreOrder::where('tenant_id', $tenant->id)->firstOrFail();
        expect($order->total_amount_cents)->toBe(30000)
            ->and($order->items)->toHaveCount(1)
            ->and($order->items->first()->quantity)->toBe(2);

        // Verify stock decremented from 5 to 3
        $product->refresh();
        expect($product->stock_quantity)->toBe(3);

        // 3. Adversarial Negative Test: Attempt to buy 5 units when only 3 remain in stock
        $overOrderResponse = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.checkout'), [
                'customer_name' => 'Kojo Antwi',
                'customer_email' => 'kojo.antwi@example.com',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5], // 5 > 3
                ],
            ]);

        // MUST be rejected with 422 Insufficient stock
        $overOrderResponse->assertStatus(422);

        // Verify stock remained exactly 3 and did not go negative
        $product->refresh();
        expect($product->stock_quantity)->toBe(3);
    });

    test('enforces cross-tenant store isolation', function (): void {
        [$userA, $tenantA] = createStoreTestContext();

        // Product belonging to Tenant B
        $tenantB = Tenant::factory()->create(['isolation_mode' => 'shared']);
        $productB = StoreProduct::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Foreign Tenant Equipment',
            'slug' => 'foreign-tenant-equipment',
            'price_cents' => 50000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // User from Tenant A attempts to checkout Product B
        $response = $this->actingAs($userA)
            ->withSession(['active_tenant_id' => $tenantA->id])
            ->post(route('store.checkout'), [
                'customer_name' => 'Intruder User',
                'customer_email' => 'intruder@example.com',
                'items' => [
                    ['product_id' => $productB->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422);

        // Product B stock must remain untouched
        $productB->refresh();
        expect($productB->stock_quantity)->toBe(10);
    });

    test('platform credit purchase is strictly idempotent on payment_reference and prevents double-crediting', function (): void {
        [$user, $tenant] = createStoreTestContext();

        $pkg = PlatformCreditPackage::create([
            'name' => 'Bulk SMS Tier 1',
            'credit_type' => 'sms',
            'amount_credits' => 1000,
            'price_cents' => 5000,
            'currency' => 'GHS',
            'is_active' => true,
        ]);

        $creditService = app(PlatformCreditService::class);

        // First purchase with reference
        $wallet1 = $creditService->purchasePackage((string) $tenant->id, $pkg->id, 'PAY-SMS-IDEMP-01');
        expect($wallet1->balance)->toBe(1000)
            ->and(PlatformCreditPurchase::where('payment_reference', 'PAY-SMS-IDEMP-01')->count())->toBe(1);

        // Replay with identical reference
        $wallet2 = $creditService->purchasePackage((string) $tenant->id, $pkg->id, 'PAY-SMS-IDEMP-01');
        expect($wallet2->balance)->toBe(1000) // Did NOT increase to 2000!
            ->and(PlatformCreditPurchase::where('payment_reference', 'PAY-SMS-IDEMP-01')->count())->toBe(1);
    });

    test('checkout enforces product audience check rejecting unauthorized guests for staff_only products', function (): void {
        [$adminUser, $tenant] = createStoreTestContext();

        $staffProduct = StoreProduct::create([
            'tenant_id' => $tenant->id,
            'name' => 'Internal Staff Uniform',
            'slug' => 'internal-staff-uniform',
            'price_cents' => 10000,
            'stock_quantity' => 10,
            'audience' => 'staff_only',
            'is_active' => true,
        ]);

        // Unauthorized external user (no store/staff permissions)
        $externalUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'external.guest@example.com',
        ]);
        $externalUser->tenants()->syncWithoutDetaching([$tenant->id]);

        $response = $this->actingAs($externalUser)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.checkout'), [
                'customer_name' => 'Guest User',
                'customer_email' => 'external.guest@example.com',
                'items' => [
                    ['product_id' => $staffProduct->id, 'quantity' => 1],
                ],
            ]);

        $response->assertForbidden();
        expect(StoreOrder::where('customer_email', 'external.guest@example.com')->count())->toBe(0);
    });

    test('order lifecycle: placed as pending with stock reserved, confirmed to paid, or cancelled with stock restored', function (): void {
        [$user, $tenant] = createStoreTestContext();

        $product = StoreProduct::create([
            'tenant_id' => $tenant->id,
            'name' => 'Stationery Pack',
            'slug' => 'stationery-pack',
            'price_cents' => 2000,
            'stock_quantity' => 10,
            'audience' => 'public',
            'is_active' => true,
        ]);

        // 1. Place order without payment reference -> creates as pending and reserves 4 units
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.checkout'), [
                'customer_name' => 'Kweku Mensah',
                'customer_email' => 'kweku@example.com',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 4],
                ],
            ])
            ->assertRedirect();

        $order = StoreOrder::where('customer_email', 'kweku@example.com')->firstOrFail();
        expect($order->status)->toBe('pending');

        $product->refresh();
        expect($product->stock_quantity)->toBe(6); // 10 - 4 reserved

        // 2. Confirm payment
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.orders.confirm-payment', $order), [
                'payment_reference' => 'PAY-CONFIRMED-999',
            ])
            ->assertRedirect();

        $order->refresh();
        expect($order->status)->toBe('paid')
            ->and($order->payment_reference)->toBe('PAY-CONFIRMED-999');

        // 3. Second order cancelled: stock restored
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.checkout'), [
                'customer_name' => 'Adwoa Mansa',
                'customer_email' => 'adwoa@example.com',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $order2 = StoreOrder::where('customer_email', 'adwoa@example.com')->firstOrFail();
        expect($order2->status)->toBe('pending');

        $product->refresh();
        expect($product->stock_quantity)->toBe(3); // 6 - 3 reserved

        // Cancel order 2
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.orders.cancel', $order2))
            ->assertRedirect();

        $order2->refresh();
        expect($order2->status)->toBe('cancelled');

        // Stock restored back to 6!
        $product->refresh();
        expect($product->stock_quantity)->toBe(6);
    });
});
