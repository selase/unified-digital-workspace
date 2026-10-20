<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Permission;
use App\Models\User;
use App\Modules\Store\Models\PlatformCreditPackage;
use App\Modules\Store\Models\PlatformCreditPurchase;
use App\Services\ModuleManager;
use Database\Seeders\RecommendedPlatformCreditPackageSeeder;

beforeEach(function (): void {
    $this->buyer = User::factory()->create();
    $this->tenant = setActiveTenantForTest($this->buyer);
    $permission = Permission::query()->firstOrCreate([
        'name' => 'store.platform.purchase', 'guard_name' => 'web',
    ], ['category' => 'store']);
    $this->buyer->givePermissionTo($permission);
    app(ModuleManager::class)->enableForTenant('store', $this->tenant);
    $this->seed(RecommendedPlatformCreditPackageSeeder::class);
    $this->actingAs($this->buyer);
});

it('lists only the tenant billing currency and passes package currency and amount to checkout', function (string $currency, int $cents): void {
    $this->tenant->update(['billing_currency' => $currency]);
    $pack = PlatformCreditPackage::query()->where('currency', $currency)->sole();
    $this->get(route('store.platform'))->assertSuccessful()->assertViewHas('packages', function ($packages) use ($currency): bool {
        return $packages->count() === 1 && $packages->every(fn ($pack): bool => $pack->currency === $currency);
    });
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('createOneTimeCheckoutSession')->once()->withArgs(
        function (string $email, int $amount, string $checkoutCurrency, string $redirect, array $metadata) use ($currency, $cents, $pack): bool {
            return $email === $this->buyer->email && $amount === $cents && $checkoutCurrency === $currency
                && $redirect === route('billing.callback') && $metadata['package_id'] === $pack->id
                && $metadata['tenant_id'] === $this->tenant->id;
        }
    )->andReturn('https://checkout.example.test/email');
    app()->instance(PaymentGateway::class, $gateway);
    $this->post(route('store.platform.purchase'), ['package_id' => $pack->id])
        ->assertRedirect('https://checkout.example.test/email');
    expect(PlatformCreditPurchase::query()->sole()->amount_paid_cents)->toBe($cents);
})->with([['GHS', 1500], ['USD', 100]]);

it('rejects a tampered other-currency pack before creating a purchase or contacting checkout', function (string $currency, string $otherCurrency): void {
    $this->tenant->update(['billing_currency' => $currency]);
    $pack = PlatformCreditPackage::query()->where('currency', $otherCurrency)->sole();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldNotReceive('createOneTimeCheckoutSession');
    app()->instance(PaymentGateway::class, $gateway);

    $this->postJson(route('store.platform.purchase'), ['package_id' => $pack->id])
        ->assertUnprocessable()->assertJsonValidationErrors('package_id');
    expect(PlatformCreditPurchase::query()->count())->toBe(0);
})->with([['GHS', 'USD'], ['USD', 'GHS']]);

it('selects the platform credit purchase gateway from tenant billing currency', function (string $currency, string $gatewayClass): void {
    $this->tenant->update(['billing_currency' => $currency]);
    config(['services.stripe.secret' => 'sk_test_local']);
    $request = Illuminate\Http\Request::create('/store/platform/purchase', 'POST');
    $route = new Illuminate\Routing\Route('POST', 'store/platform/purchase', fn () => null);
    $route->name('store.platform.purchase');
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    expect(app(PaymentGateway::class))->toBeInstanceOf($gatewayClass);
})->with([
    ['GHS', App\Services\Payment\PaystackGateway::class],
    ['USD', App\Services\Payment\StripeGateway::class],
]);

it('rejects invoice currency overrides on platform credit checkout', function (): void {
    $this->tenant->update(['billing_currency' => 'USD']);
    $pack = PlatformCreditPackage::query()->where('currency', 'USD')->sole();
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldNotReceive('createOneTimeCheckoutSession');
    app()->instance(PaymentGateway::class, $gateway);
    $this->postJson(route('store.platform.purchase'), ['package_id' => $pack->id, 'invoice_id' => 123])
        ->assertUnprocessable()->assertJsonValidationErrors('invoice_id');
    expect(PlatformCreditPurchase::query()->count())->toBe(0);
});
