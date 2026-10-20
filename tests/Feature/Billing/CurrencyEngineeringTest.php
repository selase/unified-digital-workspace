<?php

declare(strict_types=1);

use App\Enum\UsageMetric;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\UsagePrice;
use App\Models\UsageRollup;
use App\Services\Tenancy\InvoicingService;
use App\Services\Tenancy\PricingService;

it('derives currency from country and permits an explicit override', function () {
    foreach (['Ghana', 'GH', 'GHA', ' ghana '] as $country) {
        expect((new Tenant(['country' => $country]))->billingCurrency())->toBe('GHS');
    }
    expect((new Tenant(['country' => 'Nigeria']))->billingCurrency())->toBe('USD')
        ->and((new Tenant(['country' => null]))->billingCurrency())->toBe('USD')
        ->and((new Tenant(['country' => 'Ghana', 'billing_currency' => 'USD']))->billingCurrency())->toBe('USD');
});

it('keeps legacy dollars separate from explicitly configured cedis including zero', function () {
    $package = Package::factory()->create(['price' => 25, 'price_ghs' => null]);
    expect($package->priceForCurrency('USD'))->toBe(25.0);
    expect(fn () => $package->priceForCurrency('GHS'))->toThrow(DomainException::class, 'GHS');
    $package->update(['price_ghs' => 0]);
    expect($package->fresh()->priceForCurrency('GHS'))->toBe(0.0);
});

it('isolates every pricing tier by currency and observes edits immediately', function () {
    $package = Package::factory()->create();
    $tenant = Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id]);
    $metric = UsageMetric::REQUEST_COUNT;
    UsagePrice::create(['target_type' => Tenant::class, 'target_id' => $tenant->id, 'metric' => $metric, 'currency' => 'USD', 'unit_price' => 99]);
    $global = UsagePrice::create(['metric' => $metric, 'currency' => 'GHS', 'unit_price' => 2]);
    $service = new PricingService;
    expect($service->getUnitPrice($tenant, $metric)->id)->toBe($global->id);
    $rate = $package->usagePrices()->create(['metric' => $metric, 'currency' => 'GHS', 'unit_price' => 3]);
    expect($service->getUnitPrice($tenant, $metric)->id)->toBe($rate->id);
    $override = UsagePrice::create(['target_type' => Tenant::class, 'target_id' => $tenant->id, 'metric' => $metric, 'currency' => 'GHS', 'unit_price' => 4]);
    expect($service->getUnitPrice($tenant, $metric)->id)->toBe($override->id);
    $tenant->billing_currency = 'USD';
    expect((float) $service->getUnitPrice($tenant, $metric)->unit_price)->toBe(99.0);
});

it('invoices in the tenant currency and preserves period idempotency after a currency change', function () {
    $package = Package::factory()->create(['price' => 25, 'price_ghs' => 120]);
    $tenant = Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id]);
    $service = app(InvoicingService::class);
    $start = now()->startOfMonth();
    $end = now()->endOfMonth();
    $invoice = $service->generate($tenant, $start, $end);
    expect($invoice->currency)->toBe('GHS')->and((float) $invoice->subtotal)->toBe(120.0);
    expect(view('pdf.invoice', ['invoice' => $invoice->load(['tenant', 'items'])])->render())->toContain('GHS 120.00');
    $tenant->billing_currency = 'USD';
    expect($service->generate($tenant, $start, $end)->id)->toBe($invoice->id);
});

it('rolls back the entire invoice when a cedi package or used metric is unpriced', function (bool $missingPackage) {
    $package = Package::factory()->create(['price' => 25, 'price_ghs' => $missingPackage ? null : 120]);
    $tenant = Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id]);
    $start = now()->startOfMonth();
    UsagePrice::create(['metric' => UsageMetric::REQUEST_COUNT, 'currency' => 'USD', 'unit_price' => 5]);
    UsageRollup::create(['tenant_id' => $tenant->id, 'metric' => UsageMetric::REQUEST_COUNT, 'period' => 'day', 'period_start' => $start, 'value' => 10, 'dimensions_hash' => 'currency-test']);
    $invoiceCount = Invoice::count();
    $itemCount = InvoiceItem::count();
    expect(fn () => app(InvoicingService::class)->generate($tenant, $start, now()->endOfMonth()))->toThrow(DomainException::class, 'GHS');
    expect(Invoice::count())->toBe($invoiceCount)->and(InvoiceItem::count())->toBe($itemCount);
})->with([true, false]);

it('saves and removes rates only within the selected currency including explicit free rates', function () {
    $user = App\Models\User::factory()->create();
    Illuminate\Support\Facades\Gate::before(fn () => true);
    $this->actingAs($user);
    $metric = UsageMetric::REQUEST_COUNT->value;
    UsagePrice::create(['metric' => $metric, 'currency' => 'USD', 'unit_price' => 10]);
    $url = route('admin.billing.rate-cards.prices.update');
    $this->post($url, ['usage_currency' => 'GHS', 'usage_prices' => [$metric => ['unit_price' => '0', 'unit_quantity' => 1]]])->assertSessionHasNoErrors();
    expect((float) UsagePrice::where('metric', $metric)->where('currency', 'GHS')->sole()->unit_price)->toBe(0.0);
    $this->post($url, ['usage_currency' => 'GHS', 'usage_prices' => [$metric => ['unit_price' => '', 'unit_quantity' => 1]]])->assertSessionHasNoErrors();
    expect(UsagePrice::where('currency', 'GHS')->count())->toBe(0)
        ->and((float) UsagePrice::where('metric', $metric)->where('currency', 'USD')->sole()->unit_price)->toBe(10.0);
    $this->post($url, ['usage_currency' => 'EUR'])->assertSessionHasErrors('usage_currency');
    $this->post($url, ['usage_prices' => ['invented' => ['unit_price' => 1]]])->assertSessionHasErrors('usage_prices');
});

it('creates and updates a package with separate currency prices and metered cards', function () {
    Illuminate\Support\Facades\Gate::before(fn () => true);
    $this->actingAs(App\Models\User::factory()->create());
    $metric = UsageMetric::REQUEST_COUNT->value;
    $data = ['name' => 'Currency Plan', 'slug' => 'currency-plan', 'price' => '25', 'price_ghs' => '', 'interval' => 'month', 'billing_model' => 'flat_rate', 'is_active' => '0'];
    $this->post(route('packages.store'), $data)->assertSessionHasNoErrors();
    $package = Package::where('slug', 'currency-plan')->sole();
    expect($package->price_ghs)->toBeNull()->and($package->is_active)->toBeFalse();
    $package->usagePrices()->create(['metric' => $metric, 'currency' => 'USD', 'unit_price' => 10]);
    $data['price_ghs'] = '120';
    $data['usage_currency'] = 'GHS';
    $data['usage_prices'] = [$metric => ['unit_price' => '0', 'unit_quantity' => 1]];
    $this->put(route('packages.update', $package->id), $data)->assertSessionHasNoErrors();
    expect($package->fresh()->priceForCurrency('GHS'))->toBe(120.0)
        ->and($package->usagePrices()->count())->toBe(2);
    $this->get(route('packages.edit', [$package->id, 'currency' => 'GHS']))->assertSuccessful()->assertSee('Unit Price (GHS)');
    $this->get(route('packages.create'))->assertSuccessful()->assertSee('name="slug"', false)->assertSee('name="billing_model"', false);
    $data['price_ghs'] = -1;
    $this->put(route('packages.update', $package->id), $data)->assertSessionHasErrors('price_ghs');
});

it('selects platform gateways using tenant currency and persisted invoice currency', function (string $routeName) {
    $tenant = setActiveTenantForTest(null, ['country' => 'Ghana']);
    $request = Illuminate\Http\Request::create('/billing/checkout', 'POST');
    $route = new Illuminate\Routing\Route('POST', 'billing/checkout', fn () => null);
    $route->name($routeName);
    $request->setRouteResolver(fn () => $route);
    $this->app->instance('request', $request);
    expect(app(App\Contracts\PaymentGateway::class))->toBeInstanceOf(App\Services\Payment\PaystackGateway::class);
    $tenant->billing_currency = 'USD';
    app(App\Services\Tenancy\TenantContext::class)->setTenant($tenant);
    config(['services.stripe.secret' => 'sk_test_local']);
    expect(app(App\Contracts\PaymentGateway::class))->toBeInstanceOf(App\Services\Payment\StripeGateway::class);
    $invoice = Invoice::create(['tenant_id' => $tenant->id, 'currency' => 'GHS', 'number' => 'CURRENCY-GATEWAY', 'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(), 'status' => 'issued']);
    $request->merge(['invoice_id' => $invoice->id]);
    expect(app(App\Contracts\PaymentGateway::class))->toBeInstanceOf(App\Services\Payment\PaystackGateway::class);
    $tenant->billing_currency = null;
    app(App\Services\Tenancy\TenantContext::class)->setTenant($tenant);
    $invoice->update(['currency' => 'USD']);
    expect(app(App\Contracts\PaymentGateway::class))->toBeInstanceOf(App\Services\Payment\StripeGateway::class);
})->with(['billing.checkout', 'tenant.billing.checkout']);

it('averages daily gauge totals and counts monthly distinct users while summing email counters', function () {
    $tenant = Tenant::factory()->create(['country' => 'United States']);
    $other = Tenant::factory()->create();
    $start = now()->startOfMonth();
    foreach ([UsageMetric::STORAGE_BYTES, UsageMetric::DB_BYTES] as $metric) {
        foreach ([[0, 'a', 100], [0, 'b', 200], [1, 'a', 500]] as [$day, $dimension, $value]) {
            UsageRollup::create(['tenant_id' => $tenant->id, 'period' => 'day', 'period_start' => $start->copy()->addDays($day), 'metric' => $metric, 'dimensions' => ['disk' => $dimension], 'value' => $value]);
        }
    }
    foreach ([0, 1] as $day) {
        UsageRollup::create(['tenant_id' => $tenant->id, 'period' => 'day', 'period_start' => $start->copy()->addDays($day), 'metric' => UsageMetric::EMAIL_COUNT, 'value' => 5]);
    }
    foreach (['user-a', 'user-b'] as $userId) {
        UsageRollup::create(['tenant_id' => $tenant->id, 'period' => 'month', 'period_start' => $start, 'metric' => UsageMetric::USER_ACTIVE_MONTHLY, 'dimensions' => ['user_id' => $userId], 'value' => 1]);
    }
    foreach ([[$other->id, $start], [$tenant->id, $start->copy()->subMonth()]] as [$tenantId, $time]) {
        UsageRollup::create(['tenant_id' => $tenantId, 'period' => 'month', 'period_start' => $time, 'metric' => UsageMetric::USER_ACTIVE_MONTHLY, 'dimensions' => ['user_id' => 'outside'], 'value' => 1]);
    }
    $quantities = (new PricingService)->billableQuantities($tenant, $start, now()->endOfMonth());
    expect($quantities[UsageMetric::STORAGE_BYTES->value])->toBe(400.0)
        ->and($quantities)->not->toHaveKey(UsageMetric::DB_BYTES->value)
        ->and($quantities[UsageMetric::USER_ACTIVE_MONTHLY->value])->toBe(2.0)
        ->and($quantities[UsageMetric::EMAIL_COUNT->value])->toBe(10.0);
});

it('bills measured database gauge averages only for dedicated tenants', function () {
    $tenant = Tenant::factory()->create(['country' => 'United States', 'isolation_mode' => 'shared']);
    $start = now()->startOfMonth();
    foreach ([100, 300] as $day => $value) {
        UsageRollup::create(['tenant_id' => $tenant->id, 'period' => 'day', 'period_start' => $start->copy()->addDays($day), 'metric' => UsageMetric::DB_BYTES, 'value' => $value]);
    }
    $service = new PricingService;
    expect($service->billableQuantities($tenant, $start, now()->endOfMonth()))->not->toHaveKey(UsageMetric::DB_BYTES->value);
    $tenant->isolation_mode = 'db_per_tenant';
    expect($service->billableQuantities($tenant, $start, now()->endOfMonth())[UsageMetric::DB_BYTES->value])->toBe(200.0);
});

it('selects a configured usage plan and syncs entitlements without resolving a gateway', function () {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana']);
    grantOrganisationAdminAccess($user);
    $package = Package::factory()->create(['slug' => 'team', 'price' => 0, 'price_ghs' => 0, 'is_active' => true]);
    $feature = App\Models\Feature::factory()->create(['slug' => 'custom-domains']);
    $package->features()->attach($feature->id);
    $this->app->bind(App\Contracts\PaymentGateway::class, fn () => throw new RuntimeException('Gateway must not be resolved for usage plans.'));
    $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'team'])->assertRedirect(route('tenant.billing.index'));
    expect($tenant->fresh()->package_id)->toEqual($package->id)
        ->and(App\Models\TenantFeature::where('tenant_id', $tenant->id)->where('feature_key', 'custom-domains')->where('enabled', true)->exists())->toBeTrue()
        ->and(App\Models\TenantModule::where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(App\Models\Subscription::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('rejects unconfigured or paid usage plans and requires billing permission', function () {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana']);
    $package = Package::factory()->create(['slug' => 'team', 'price' => 0, 'price_ghs' => null, 'is_active' => true]);
    $originalPackageId = $tenant->package_id;
    $this->app->bind(App\Contracts\PaymentGateway::class, fn () => throw new RuntimeException('Gateway must not resolve.'));
    $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'team'])->assertForbidden();
    grantOrganisationAdminAccess($user);
    foreach ([null, 10] as $price) {
        $package->update(['price_ghs' => $price]);
        $this->post(route('billing.checkout'), ['plan' => 'team'])->assertRedirect()->assertSessionHas('error');
        expect($tenant->fresh()->package_id)->toEqual($originalPackageId);
    }
    $package->update(['price_ghs' => 0, 'is_active' => false]);
    $this->post(route('billing.checkout'), ['plan' => 'team'])->assertNotFound();
});

it('preserves checkout for an unknown legacy provider plan identifier', function () {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['country' => 'United States', 'meta' => ['stripe_id' => 'cus_legacy']]);
    grantOrganisationAdminAccess($user);
    $gateway = Mockery::mock(App\Contracts\PaymentGateway::class);
    $gateway->shouldReceive('createCheckoutSession')->once()->with('cus_legacy', 'price_premium', Mockery::type('string'))->andReturn('https://checkout.stripe.com/legacy');
    $this->swap(App\Contracts\PaymentGateway::class, $gateway);
    $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'price_premium'])->assertRedirect('https://checkout.stripe.com/legacy');
});

it('blocks changing an assigned usage plan after current month metering without changing entitlements', function (string $source) {
    $this->travelTo(Carbon\Carbon::parse('2026-10-20 12:00:00'));
    $user = App\Models\User::factory()->create();
    $team = Package::factory()->create(['slug' => 'team', 'price' => 0, 'price_ghs' => 0]);
    $community = Package::factory()->create(['slug' => 'community', 'price' => 0, 'price_ghs' => 0]);
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana', 'package_id' => $team->id]);
    grantOrganisationAdminAccess($user);
    $feature = App\Models\Feature::factory()->create(['slug' => 'custom-domains']);
    $team->features()->attach($feature->id);
    $tenant->syncFeaturesFromPackage();
    if ($source === 'event') {
        App\Models\UsageEvent::create(['tenant_id' => $tenant->id, 'occurred_at' => now(), 'type' => UsageMetric::EMAIL_COUNT, 'quantity' => 1, 'unit' => 'count']);
    } else {
        UsageRollup::create(['tenant_id' => $tenant->id, 'period' => $source, 'period_start' => now()->startOfMonth(), 'metric' => UsageMetric::USER_ACTIVE_MONTHLY, 'value' => 1]);
    }
    $this->app->bind(App\Contracts\PaymentGateway::class, fn () => throw new RuntimeException('Blocked plan changes must not resolve a gateway.'));
    $this->actingAs($user)->post(route('billing.checkout'), ['plan' => $community->slug])->assertRedirect()->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Contact an administrator'));
    expect($tenant->fresh()->package_id)->toEqual($team->id)
        ->and(App\Models\TenantFeature::where('tenant_id', $tenant->id)->where('feature_key', 'custom-domains')->where('enabled', true)->exists())->toBeTrue();
})->with(['event', 'minute', 'day', 'month']);

it('allows initial setup despite usage and ignores other tenants and previous month usage', function (bool $initialSetup) {
    $this->withoutMiddleware([App\Http\Middleware\TrackActiveUser::class, App\Http\Middleware\MeterRequestUsage::class]);
    $this->travelTo(Carbon\Carbon::parse('2026-10-20 12:00:00'));
    $user = App\Models\User::factory()->create();
    $oldPackage = Package::factory()->create();
    $team = Package::factory()->create(['slug' => 'team', 'price' => 0, 'price_ghs' => 0]);
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana', 'package_id' => $initialSetup ? null : $oldPackage->id]);
    grantOrganisationAdminAccess($user);
    $other = Tenant::factory()->create();
    foreach ([[$other->id, now()], [$tenant->id, $initialSetup ? now() : now()->startOfMonth()->subSecond()]] as [$tenantId, $time]) {
        App\Models\UsageEvent::create(['tenant_id' => $tenantId, 'occurred_at' => $time, 'type' => UsageMetric::EMAIL_COUNT, 'quantity' => 1, 'unit' => 'count']);
        UsageRollup::create(['tenant_id' => $tenantId, 'period' => 'day', 'period_start' => $time->copy()->startOfDay(), 'metric' => UsageMetric::EMAIL_COUNT, 'value' => 1]);
    }
    $this->app->bind(App\Contracts\PaymentGateway::class, fn () => throw new RuntimeException('Usage plan selection must not resolve a gateway.'));
    $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'team'])->assertRedirect(route('tenant.billing.index'))->assertSessionHas('success');
    expect($tenant->fresh()->package_id)->toEqual($team->id);
})->with([true, false]);

it('restricts explicit currency overrides to supplier administrators', function (bool $supplierAdmin) {
    $user = App\Models\User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana', 'billing_currency' => null, 'meta' => ['tax_id' => 'EXISTING-TAX-ID', 'billing_address' => 'Existing address']]);
    grantOrganisationAdminAccess($user);
    if ($supplierAdmin) {
        Illuminate\Support\Facades\Gate::before(fn ($user, string $ability): ?bool => $ability === 'access-superadmin-dashboard' ? true : null);
    }
    $this->actingAs($user);
    $view = $this->get(route('tenant.settings.billing', ['subdomain' => $tenant->slug]))->assertSuccessful();
    if ($supplierAdmin) {
        $view->assertSee('name="billing_currency"', false);
    } else {
        $view->assertDontSee('name="billing_currency"', false)->assertSee('Only a supplier administrator can override');
    }
    $response = $this->from(route('tenant.settings.billing', ['subdomain' => $tenant->slug]))->post(route('tenant.settings.billing.update', ['subdomain' => $tenant->slug]), ['billing_email' => 'billing@example.test', 'billing_currency' => 'USD']);
    if ($supplierAdmin) {
        $response->assertRedirect(route('tenant.settings.billing', ['subdomain' => $tenant->slug]))->assertSessionHas('success')->assertSessionHasNoErrors();
        expect($tenant->fresh()->billingCurrency())->toBe('USD')
            ->and($tenant->fresh()->meta['tax_id'])->toBe('EXISTING-TAX-ID')
            ->and($tenant->fresh()->meta['billing_address'])->toBe('Existing address');
        $this->post(route('tenant.settings.billing.update', ['subdomain' => $tenant->slug]), ['billing_email' => 'billing@example.test', 'tax_id' => null, 'billing_address' => null])
            ->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();
        expect($tenant->fresh()->meta['tax_id'])->toBeNull()->and($tenant->fresh()->meta['billing_address'])->toBeNull();
    } else {
        $response->assertSessionHasErrors('billing_currency');
        expect($tenant->fresh()->billingCurrency())->toBe('GHS');
    }
})->with([true, false]);

it('blocks an assigned plan change when the normal request records current active-user usage', function () {
    $user = App\Models\User::factory()->create();
    $team = Package::factory()->create(['slug' => 'team', 'price' => 0, 'price_ghs' => 0]);
    Package::factory()->create(['slug' => 'community', 'price' => 0, 'price_ghs' => 0]);
    $tenant = setActiveTenantForTest($user, ['country' => 'Ghana', 'package_id' => $team->id]);
    grantOrganisationAdminAccess($user);
    $this->app->bind(App\Contracts\PaymentGateway::class, fn () => throw new RuntimeException('Blocked plan changes must not resolve a gateway.'));
    $this->actingAs($user)->post(route('billing.checkout'), ['plan' => 'community'])
        ->assertRedirect()->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Contact an administrator'));
    expect($tenant->fresh()->package_id)->toEqual($team->id)
        ->and(App\Models\UsageEvent::where('tenant_id', $tenant->id)->where('type', UsageMetric::USER_ACTIVE_DAILY)->exists())->toBeTrue();
});
