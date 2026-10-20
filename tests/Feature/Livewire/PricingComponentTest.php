<?php

declare(strict_types=1);

use App\Livewire\PricingTable;
use Livewire\Livewire;

it('shows a paid core subscription without trial or free service promises', function (): void {
    Livewire::test(PricingTable::class)
        ->assertSee('Core Workspace')->assertSee('required')
        ->assertSee('GHS 300.00 / USD 25.00')
        ->assertSee('even with no staff logins')
        ->assertDontSee('no monthly base charge')
        ->assertDontSee('14-day')->assertDontSee('2 months free')
        ->assertDontSee('SSO')->assertDontSee('SAML')->assertDontSee('retention');
});

it('shows independently configured access package and resource prices', function (): void {
    $component = Livewire::test(PricingTable::class);
    foreach (config('usage-pricing.hybrid.access') as $rate) {
        $component->assertSee(number_format($rate['GHS'], 2))->assertSee(number_format($rate['USD'], 2));
    }
    foreach (config('usage-pricing.hybrid.packages') as $package) {
        $component->assertSee($package['name']);
    }
    $component->assertSee('custom domains')->assertSee('Commerce')
        ->assertSee('Approved additional storage: GHS 1.50 / USD 0.10')
        ->assertSee('Dedicated database: GHS 3.00 / USD 0.20')
        ->assertSee('Email: GHS 15.00 / USD 1.00')
        ->assertSee('excludes prepaid and system mail');
});

it('describes marginal volume discounts rather than advertising a cumulative upgrade', function (): void {
    Livewire::test(PricingTable::class)
        ->assertSee('Calculated separately for each access level')
        ->assertSee('first 100 users pay full rate')
        ->assertSee('Packages are independent')
        ->assertDontSee('Most Popular');
});
