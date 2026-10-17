<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\StoreOrder;
use App\Modules\Store\Models\StoreProduct;
use App\Modules\Store\Models\TenantCreditWallet;
use App\Modules\Store\Services\PlatformCreditService;
use App\Services\ModuleManager;
use Spatie\Permission\Models\Permission;

function createStoreTestContext(): array
{
    $user = User::factory()->create([
        'email' => 'store-admin-'.uniqid().'@example.com',
    ]);
    $tenant = setActiveTenantForTest($user);

    foreach (['store.view', 'store.manage', 'store.platform.purchase', 'store.orders.manage'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Illuminate\Support\Str::uuid(), 'category' => 'store']);
    }

    setPermissionsTeamId($tenant->id);
    $user->givePermissionTo(['store.view', 'store.manage', 'store.platform.purchase', 'store.orders.manage']);
    app(ModuleManager::class)->enableForTenant('store', $tenant);

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

        // 2. Purchase SMS Package
        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.platform.purchase'), [
                'package_id' => $smsPackage->id,
            ]);

        $response->assertRedirect()
            ->assertSessionHas('status', 'success');

        $smsWallet = TenantCreditWallet::where('tenant_id', $tenant->id)->where('credit_type', 'sms')->firstOrFail();
        expect($smsWallet->balance)->toBe(5000)
            ->and($smsWallet->total_purchased)->toBe(5000);

        // 3. Purchase AI Package
        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('store.platform.purchase'), [
                'package_id' => $aiPackage->id,
            ])
            ->assertRedirect();

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
});
