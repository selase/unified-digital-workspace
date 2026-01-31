<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Config;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('redirects to provider checkout url', function () {
    Config::set('services.payment.default', 'stripe');

    // Setup Tenant & User
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['meta' => ['stripe_id' => 'cus_123']]);
    $this->withSession(['active_tenant_id' => $tenant->id]);
    $this->actingAs($user);

    // Mock Gateway
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn('https://checkout.stripe.com/test');

    $this->swap(PaymentGateway::class, $gateway);

    $response = $this->post(route('billing.checkout', ['subdomain' => $tenant->slug]), [
        'plan' => 'price_premium',
    ]);

    $response->assertRedirect('https://checkout.stripe.com/test');
});

it('creates customer if missing before checkout', function () {
    Config::set('services.payment.default', 'stripe');

    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['meta' => []]);
    $this->withSession(['active_tenant_id' => $tenant->id]);
    $this->actingAs($user);

    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('createCustomer')
        ->once()
        ->andReturn('cus_new_123');

    $gateway->shouldReceive('createCheckoutSession')
        ->once()
        ->with('cus_new_123', 'price_premium', Mockery::any())
        ->andReturn('https://checkout.stripe.com/test');

    $this->swap(PaymentGateway::class, $gateway);

    $this->post('/billing/checkout', ['plan' => 'price_premium']);

    $tenant->refresh();
    expect($tenant->meta['stripe_id'])->toBe('cus_new_123');
});
