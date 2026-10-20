<?php

declare(strict_types=1);

use App\Models\Tax;
use App\Models\User;
use Database\Seeders\GhanaTaxSeeder;
use Illuminate\Support\Facades\Gate;

it('preserves legacy global flat and compound calculations', function (): void {
    Tax::create(['name' => 'Global flat', 'rate' => 10, 'priority' => 1]);
    Tax::create(['name' => 'Global compound', 'rate' => 10, 'priority' => 2, 'is_compound' => true]);
    Tax::create(['name' => 'Ghana only', 'rate' => 15, 'country_code' => 'GH', 'is_active' => true]);

    expect(Tax::calculateFor(100)['total_tax'])->toEqual(21)
        ->and(Tax::calculateFor(100, 'US', true)['total_tax'])->toEqual(21)
        ->and(Tax::calculateFor(100, 'GH')['total_tax'])->toEqual(21);
});

it('seeds only the three Ghana 2026 standard rates disabled without changing existing taxes', function (): void {
    $legacy = Tax::create(['name' => 'VAT', 'rate' => 12, 'is_active' => true]);
    $this->seed(GhanaTaxSeeder::class);
    $this->seed(GhanaTaxSeeder::class);
    $ghana = Tax::query()->where('country_code', 'GH')->orderBy('priority')->get();

    expect($ghana)->toHaveCount(3)
        ->and($ghana->pluck('name')->all())->toBe(['NHIL', 'GETFund Levy', 'VAT'])
        ->and($ghana->pluck('rate')->all())->toBe(['2.50', '2.50', '15.00'])
        ->and($ghana->every(fn (Tax $tax): bool => ! $tax->is_compound && ! $tax->is_active))->toBeTrue()
        ->and($legacy->refresh()->country_code)->toBeNull()
        ->and($legacy->rate)->toBe('12.00')
        ->and($legacy->is_active)->toBeTrue();

    $ghana->first()->update(['rate' => 1, 'is_active' => true]);
    $this->seed(GhanaTaxSeeder::class);
    expect($ghana->first()->refresh()->rate)->toBe('1.00')
        ->and($ghana->first()->is_active)->toBeTrue();
});

it('applies Ghana standard rates to the same base only after activation and explicit applicability', function (): void {
    $this->seed(GhanaTaxSeeder::class);
    expect(Tax::calculateFor(1000, 'GH', true)['total_tax'])->toEqual(0);
    Tax::query()->where('country_code', 'GH')->update(['is_active' => true]);

    $result = Tax::calculateFor(1000, ' gh ', true);
    expect($result['total_tax'])->toEqual(200)
        ->and(array_column($result['taxes'], 'amount'))->toEqual([25, 25, 150]);

    foreach ([[null, true], ['US', true], ['GH', false]] as [$country, $applicable]) {
        expect(Tax::calculateFor(1000, $country, $applicable)['total_tax'])->toEqual(0);
    }

    expect(Tax::calculateFor(0, 'GH', true)['total_tax'])->toEqual(0)
        ->and(Tax::calculateFor(1000, 'Ghana', true)['total_tax'])->toEqual(200);
});

it('supports an explicit exemption from all taxes without changing legacy defaults', function (): void {
    Tax::create(['name' => 'Global tax', 'rate' => 10]);
    Tax::create(['name' => 'VAT', 'rate' => 15, 'country_code' => 'GH']);

    expect(Tax::calculateFor(100, 'GH', true, true))->toBe(['taxes' => [], 'total_tax' => 0.0])
        ->and(Tax::calculateFor(100)['total_tax'])->toEqual(10);
});

it('creates and updates country tax rules through the admin form', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (): bool => true);

    $this->post(route('admin.billing.rate-cards.taxes.store'), [
        'name' => 'Ghana VAT', 'rate' => 15, 'priority' => 1, 'country_code' => ' gh ',
    ])->assertRedirect();
    $tax = Tax::query()->where('name', 'Ghana VAT')->sole();
    expect($tax->country_code)->toBe('GH')->and($tax->is_active)->toBeFalse();

    $this->put(route('admin.billing.rate-cards.taxes.update', $tax), [
        'name' => 'Ghana VAT', 'rate' => 15, 'priority' => 1, 'country_code' => 'GH',
        'is_active' => '0', 'is_compound' => '0',
    ])->assertRedirect();
    expect($tax->refresh()->is_active)->toBeFalse()->and($tax->is_compound)->toBeFalse();

    $this->put(route('admin.billing.rate-cards.taxes.update', $tax), [
        'name' => 'Ghana VAT', 'rate' => 15, 'priority' => 1, 'country_code' => 'GH', 'is_active' => '1',
    ])->assertRedirect();
    expect($tax->refresh()->country_code)->toBe('GH')->and($tax->is_active)->toBeTrue();

    $this->get(route('admin.billing.rate-cards.index'))->assertOk()
        ->assertSee('GH')->assertSee('VAT registration')->assertSee('name="country_code"', false);
});

it('keeps omitted country global and active when creating legacy tax rules', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (): bool => true);
    $this->post(route('admin.billing.rate-cards.taxes.store'), [
        'name' => 'Legacy rule', 'rate' => 5, 'priority' => 1,
    ])->assertRedirect();
    $tax = Tax::query()->where('name', 'Legacy rule')->sole();
    expect($tax->country_code)->toBeNull()->and($tax->is_active)->toBeTrue();
});

it('rejects invalid tax country codes and invalid numeric or boolean values', function (array $invalid, string $field): void {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (): bool => true);
    $this->postJson(route('admin.billing.rate-cards.taxes.store'), $invalid + [
        'name' => 'VAT', 'rate' => 15, 'priority' => 1, 'country_code' => 'GH',
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['country_code' => 'GHA'], 'country_code'],
    [['country_code' => '12'], 'country_code'],
    [['rate' => -1], 'rate'],
    [['rate' => 1000], 'rate'],
    [['is_active' => 'yes'], 'is_active'],
]);

it('preserves a country restriction when an older admin client omits country on update', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::before(fn (): bool => true);
    $tax = Tax::create(['name' => 'VAT', 'rate' => 15, 'country_code' => 'GH']);
    $this->put(route('admin.billing.rate-cards.taxes.update', $tax), [
        'name' => 'VAT', 'rate' => 15, 'priority' => 1, 'is_active' => 1,
    ])->assertRedirect();
    expect($tax->refresh()->country_code)->toBe('GH');
});

it('adds country support without changing legacy tax data', function (): void {
    $tax = Tax::create(['name' => 'Legacy', 'rate' => 10, 'is_compound' => true]);
    $migration = require database_path('migrations/landlord/2026_10_20_090001_add_country_code_to_taxes_table.php');
    $migration->down();
    $migration->up();

    expect($tax->refresh()->country_code)->toBeNull()
        ->and($tax->rate)->toBe('10.00')
        ->and($tax->is_compound)->toBeTrue()
        ->and($tax->is_active)->toBeTrue();
});

it('refuses rollback that would turn country-specific taxes into global taxes', function (): void {
    Tax::create(['name' => 'VAT', 'rate' => 15, 'country_code' => 'GH']);
    $migration = require database_path('migrations/landlord/2026_10_20_090001_add_country_code_to_taxes_table.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'would make them global');
    expect(Tax::query()->sole()->country_code)->toBe('GH');
});

it('forbids tax changes by ordinary users', function (): void {
    $this->actingAs(User::factory()->create());
    $tax = Tax::create(['name' => 'Existing rule', 'rate' => 10]);
    $this->postJson(route('admin.billing.rate-cards.taxes.store'), [])->assertForbidden();
    $this->putJson(route('admin.billing.rate-cards.taxes.update', $tax), [])->assertForbidden();
    $this->deleteJson(route('admin.billing.rate-cards.taxes.destroy', $tax))->assertForbidden();
});

it('invoices country taxes only with supplier opt-in and respects administrator exemptions', function (string $country, bool $enabled, bool $exempt, float $expectedTax): void {
    config(['billing.country_taxes_enabled' => $enabled]);
    $this->seed(GhanaTaxSeeder::class);
    Tax::query()->where('country_code', 'GH')->update(['is_active' => true]);
    $package = App\Models\Package::factory()->create(['price' => 1000, 'price_ghs' => 1000]);
    $tenant = App\Models\Tenant::factory()->create([
        'country' => $country, 'package_id' => $package->id, 'meta' => ['tax_exempt' => $exempt],
    ]);
    $invoice = app(App\Services\Tenancy\InvoicingService::class)->generate($tenant, now()->startOfMonth(), now()->endOfMonth());

    expect((float) $invoice->subtotal)->toBe(1000.0)
        ->and((float) $invoice->tax_total)->toBe($expectedTax)
        ->and((float) $invoice->total)->toBe(1000.0 + $expectedTax);
})->with([
    ['Ghana', false, false, 0.0],
    ['GH', true, false, 200.0],
    ['US', true, false, 0.0],
    ['GH', true, true, 0.0],
]);

it('uses the same applicability policy for administrator invoice adjustments', function (bool $exempt): void {
    config(['billing.country_taxes_enabled' => true]);
    $this->seed(GhanaTaxSeeder::class);
    Tax::query()->where('country_code', 'GH')->update(['is_active' => true]);
    $package = App\Models\Package::factory()->create(['price' => 1000, 'price_ghs' => 1000]);
    $tenant = App\Models\Tenant::factory()->create([
        'country' => 'GH', 'package_id' => $package->id, 'meta' => ['tax_exempt' => $exempt],
    ]);
    $invoice = app(App\Services\Tenancy\InvoicingService::class)->generate($tenant, now()->startOfMonth(), now()->endOfMonth());
    $method = new ReflectionMethod(App\Http\Controllers\Admin\InvoiceController::class, 'recalculateTotals');
    $method->invoke(app(App\Http\Controllers\Admin\InvoiceController::class), $invoice);
    expect((float) $invoice->refresh()->tax_total)->toBe($exempt ? 0.0 : 200.0);
})->with([false, true]);

it('does not accept tenant supplied exemptions in billing settings', function (): void {
    config(['billing.country_taxes_enabled' => false]);
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, ['meta' => ['tax_exempt' => false]]);
    grantOrganisationAdminAccess($user);
    $this->actingAs($user)->post(route('tenant.settings.billing.update', ['subdomain' => $tenant->slug]), [
        'billing_email' => 'accounts@example.com', 'tax_id' => '', 'billing_address' => '',
        'tax_exempt' => true, 'meta' => ['tax_exempt' => true], 'country_taxes_enabled' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($tenant->refresh()->meta['tax_exempt'])->toBeFalse()
        ->and(config('billing.country_taxes_enabled'))->toBeFalse();
});

it('rounds each Ghana tax line before summing persisted invoice totals', function (): void {
    config(['billing.country_taxes_enabled' => true]);
    $this->seed(GhanaTaxSeeder::class);
    Tax::query()->where('country_code', 'GH')->update(['is_active' => true]);
    $package = App\Models\Package::factory()->create(['price' => 253, 'price_ghs' => 253]);
    $tenant = App\Models\Tenant::factory()->create(['country' => 'GH', 'package_id' => $package->id]);
    $invoice = app(App\Services\Tenancy\InvoicingService::class)->generate($tenant, now()->startOfMonth(), now()->endOfMonth());
    $invoice->refresh();

    expect(array_column($invoice->tax_details, 'amount'))->toEqual([6.33, 6.33, 37.95])
        ->and((float) $invoice->tax_total)->toBe(50.61)
        ->and((float) $invoice->total)->toBe(303.61)
        ->and(round(array_sum(array_column($invoice->tax_details, 'amount')), 2))->toBe((float) $invoice->tax_total);

    $recalculate = new ReflectionMethod(App\Http\Controllers\Admin\InvoiceController::class, 'recalculateTotals');
    $recalculate->invoke(app(App\Http\Controllers\Admin\InvoiceController::class), $invoice);
    expect((float) $invoice->refresh()->tax_total)->toBe(50.61)
        ->and((float) $invoice->total)->toBe(303.61);
});

it('uses rounded preceding tax lines as the base for compound taxes', function (): void {
    Tax::create(['name' => 'First levy', 'rate' => 2.5, 'priority' => 1]);
    Tax::create(['name' => 'Compound tax', 'rate' => 15, 'priority' => 2, 'is_compound' => true]);
    $result = Tax::calculateFor(253);

    expect(array_column($result['taxes'], 'amount'))->toEqual([6.33, 38.90])
        ->and($result['total_tax'])->toBe(45.23);
});
