<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantDiscount;
use App\Models\User;
use App\Services\Tenancy\InvoicingService;
use Carbon\Carbon;

function discountedTenant(float $price = 1000): Tenant
{
    $package = Package::factory()->create(['price' => $price, 'price_ghs' => $price]);

    return Tenant::factory()->create(['country' => 'Ghana', 'package_id' => $package->id]);
}

function giveDiscount(Tenant $tenant, array $overrides = []): TenantDiscount
{
    return TenantDiscount::create([
        'tenant_id' => $tenant->id, 'name' => 'Loyalty', 'reason' => 'Agreed', 'type' => 'percent', 'value' => 50,
        'starts_on' => '2026-01-01', ...$overrides,
    ]);
}

function generateFor(Tenant $tenant): Invoice
{
    return app(InvoicingService::class)->generate($tenant, Carbon::parse('2026-09-01')->startOfMonth(), Carbon::parse('2026-09-30')->endOfMonth());
}

function superadmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

    return $user;
}

it('takes a percentage off the generated invoice as its own line', function (): void {
    $tenant = discountedTenant();
    giveDiscount($tenant);

    $invoice = generateFor($tenant);

    expect((float) $invoice->subtotal)->toBe(500.0)->and((float) $invoice->total)->toBe(500.0)
        ->and($invoice->items->firstWhere('meta.type', 'discount')->description)->toContain('Loyalty')->toContain('50%');
});

it('applies a fixed discount only in its own currency and never below zero', function (): void {
    $tenant = discountedTenant(100);
    giveDiscount($tenant, ['type' => 'fixed', 'value' => 400, 'currency' => 'GHS']);
    expect((float) generateFor($tenant)->total)->toBe(0.0);

    $other = discountedTenant(100);
    giveDiscount($other, ['type' => 'fixed', 'value' => 40, 'currency' => 'USD']);
    expect((float) generateFor($other)->total)->toBe(100.0);
});

it('ignores switched-off and out-of-period discounts', function (): void {
    $off = discountedTenant();
    giveDiscount($off, ['is_active' => false]);
    $expired = discountedTenant();
    giveDiscount($expired, ['ends_on' => '2026-08-31']);
    $future = discountedTenant();
    giveDiscount($future, ['starts_on' => '2026-10-01']);

    foreach ([$off, $expired, $future] as $tenant) {
        expect((float) generateFor($tenant)->total)->toBe(1000.0);
    }
});

it('lets a superadmin create and switch off a discount', function (): void {
    $tenant = discountedTenant();

    $this->actingAs(superadmin())->post(route('admin.billing.discounts.store'), [
        'tenant_id' => $tenant->id, 'name' => 'Launch', 'reason' => 'Call 8 Nov', 'type' => 'percent', 'value' => 25, 'starts_on' => '2026-11-01',
    ])->assertRedirect();

    $discount = TenantDiscount::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($discount->is_active)->toBeTrue();

    $this->patch(route('admin.billing.discounts.toggle', $discount))->assertRedirect();
    expect($discount->fresh()->is_active)->toBeFalse();
});

it('rejects invalid discounts and non-superadmins', function (): void {
    $tenant = discountedTenant();
    $payload = ['tenant_id' => $tenant->id, 'name' => 'x', 'reason' => 'y', 'type' => 'percent', 'value' => 150, 'starts_on' => '2026-11-01'];

    $this->actingAs(superadmin())->post(route('admin.billing.discounts.store'), $payload)->assertSessionHasErrors('value');
    $this->post(route('admin.billing.discounts.store'), [...$payload, 'type' => 'fixed', 'value' => 10])->assertSessionHasErrors('currency');

    $this->actingAs(User::factory()->create())->post(route('admin.billing.discounts.store'), [...$payload, 'value' => 10])->assertForbidden();
    expect(TenantDiscount::count())->toBe(0);
});
