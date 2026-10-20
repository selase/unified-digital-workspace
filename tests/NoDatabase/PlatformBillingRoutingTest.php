<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Models\Tenant;
use App\Services\Payment\StripeGateway;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(TestCase::class);

it('routes USD platform payments through Stripe on both billing route aliases', function (string $name): void {
    config(['database.connections' => [], 'services.payment.default' => 'paystack', 'services.stripe.secret' => 'sk_test_local']);
    app(TenantContext::class)->setTenant(new Tenant(['country' => 'United States']));
    $request = Request::create('/billing/checkout', 'POST');
    $route = new Route('POST', 'billing/checkout', fn (): null => null);
    $route->name($name);
    $request->setRouteResolver(fn (): Route => $route);
    $this->app->instance('request', $request);

    expect(app(PaymentGateway::class))->toBeInstanceOf(StripeGateway::class);
})->with(['billing.checkout', 'tenant.billing.checkout']);
