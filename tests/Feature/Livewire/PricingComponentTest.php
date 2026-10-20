<?php

declare(strict_types=1);

use App\Livewire\PricingTable;
use Livewire\Livewire;

it('shows monthly usage pricing without annual or trial promises', function (): void {
    Livewire::test(PricingTable::class)
        ->assertSee('per active user / month')
        ->assertSee('no monthly base charge')
        ->assertDontSee('14-day')
        ->assertDontSee('2 months free')
        ->assertDontSee('Yearly')
        ->assertDontSee('SSO')
        ->assertDontSee('SAML')
        ->assertDontSee('retention');
});

it('shows independently configured GHS and USD retail rates and modules', function (): void {
    $plans = config('product-page.plans');
    expect(array_column($plans, 'name'))->toBe(['Community', 'Website', 'Team', 'Institution', 'Marketplace']);
    $component = Livewire::test(PricingTable::class);
    foreach (config('usage-pricing.active_users') as $prices) {
        $component->assertSee('GHS '.number_format($prices['GHS'], 2))
            ->assertSee('USD '.number_format($prices['USD'], 2));
    }
    $component->assertSee('Crm')->assertSee('Custom domains included.')
        ->assertSee('Storage: GHS 1.50 / USD 0.10')
        ->assertSee('Database: GHS 3.00 / USD 0.20')
        ->assertSee('Email: GHS 15.00 / USD 1.00')
        ->assertSee('excludes prepaid and system mail');
});

it('highlights the Team plan', function (): void {
    $plans = config('product-page.plans');
    $popular = array_values(array_filter($plans, fn (array $plan): bool => $plan['most_popular']));
    expect(array_column($popular, 'name'))->toBe(['Team']);
    Livewire::test(PricingTable::class)->assertSee('Team')->assertSeeHtml('data-most-popular="true"');
});
