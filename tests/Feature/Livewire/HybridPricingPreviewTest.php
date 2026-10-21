<?php

declare(strict_types=1);

use App\Livewire\PricingTable;
use Livewire\Livewire;

it('provides a hospital example separate from current billing', function (): void {
    Livewire::test(PricingTable::class)
        ->assertSee('Hybrid pricing preview')
        ->assertSee('Does not change your subscription or invoice')
        ->call('hospitalExample')
        ->assertSet('selfServiceUsers', 1350)
        ->assertSet('operationalUsers', 150)
        ->assertSee('12,625.00')->assertSee('1,175.00');
});

it('recalculates currencies and selected packages', function (): void {
    Livewire::test(PricingTable::class)
        ->set('selfServiceUsers', 0)->set('operationalUsers', 5)
        ->set('selectedPackages', ['commerce'])->assertSee('1,175.00')
        ->set('quoteCurrency', 'USD')->assertSee('95.00');
});

it('shows validation errors rather than an invalid estimate', function (): void {
    Livewire::test(PricingTable::class)->set('selfServiceUsers', -1)
        ->assertSee('Check your inputs to calculate an estimate')
        ->assertSee('Self-service users must be a whole number');
});

it('rejects tampered currencies and package selections without a server error', function (): void {
    Livewire::test(PricingTable::class)->set('quoteCurrency', 'EUR')->assertSee('Choose GHS or USD');
    Livewire::test(PricingTable::class)->set('selectedPackages', ['ai'])->assertSee('Choose a published module package');
});

it('rejects fractional empty and excessive user counts', function (string $value): void {
    Livewire::test(PricingTable::class)->set('selfServiceUsers', $value)
        ->assertSee('Check your inputs to calculate an estimate');
})->with(['', '1.5', '100001', 'not-a-number']);

it('exposes the preview from the existing billing pricing page', function (): void {
    $tenant = App\Models\Tenant::factory()->create();
    $html = view('billing.pricing', ['tenant' => $tenant, 'packages' => collect(), 'currentPackage' => null])->render();
    expect($html)->toContain(route('product.template').'#pricing')
        ->toContain('Preview hybrid pricing and estimate your costs');
});

it('renders paid pricing publicly without a free workspace promise', function (): void {
    $this->get(route('product.template'))->assertSuccessful()
        ->assertSee('Hybrid pricing preview')
        ->assertSee('GHS 300.00 / USD 25.00')
        ->assertSee('Compare plans &amp; estimate costs', false)
        ->assertDontSee('Start for free')
        ->assertDontSee('no monthly base charge');
});

it('switches website plans and shows first-month setup separately', function (): void {
    Livewire::test(PricingTable::class)
        ->set('websiteEditors', 5)->set('websitePlan', 'managed_business')
        ->assertSee('600.00')->assertSee('1,600.00')
        ->set('includeSetup', false)->assertSee('600.00')
        ->set('quoteCurrency', 'USD')->assertSee('50.00');
});

it('revalidates capacity when changing to a smaller website plan', function (): void {
    Livewire::test(PricingTable::class)
        ->set('websitePlan', 'managed_business')->set('fileStorageGiB', '10')
        ->assertDontSee('Check your inputs to calculate an estimate')
        ->set('websitePlan', 'essentials')
        ->assertSee('File storage exceeds')->assertSee('capacity quote');
});

it('rejects an invalid website selection and cleared usage input', function (): void {
    Livewire::test(PricingTable::class)->set('websitePlan', 'free')->assertSee('Choose a website plan');
    Livewire::test(PricingTable::class)->set('fileStorageGiB', '')->assertSee('Check your inputs to calculate an estimate');
});

it('shows saved agreement guidance instead of direct legacy switching instructions', function (): void {
    $agreement = App\Models\TenantBillingAgreement::factory()->create();
    $html = view('billing.pricing', [
        'tenant' => $agreement->tenant, 'packages' => collect(), 'currentPackage' => null,
        'billingAgreement' => $agreement,
    ])->render();

    expect($html)->toContain('Your Billing Agreement')
        ->toContain('Your saved agreement controls billing and access')
        ->not->toContain('Direct plan changes are available before usage');
});

it('presents pricing in the workspace theme without template claims or sample testimonials', function (): void {
    config(['product-page.testimonials' => [[
        'quote' => 'UNVERIFIED_CUSTOMER_CLAIM', 'name' => 'Sample Person', 'role' => 'CEO', 'company' => 'Sample Company',
    ]]]);

    $this->get(route('product.template'))->assertSuccessful()
        ->assertSee('Plans &amp; pricing — Unified Digital Workspace', false)
        ->assertSee('A website first. A workspace when you need it.')
        ->assertSee('How billing works')
        ->assertSee('Existing customer agreements keep their agreed rates.')
        ->assertSee('GHS 300.00 / USD 25.00')
        ->assertSee('GHS 600.00 / USD 50.00')
        ->assertDontSee('StarterKit')
        ->assertDontSee('SaaS in days')
        ->assertDontSee('UNVERIFIED_CUSTOMER_CLAIM')
        ->assertDontSee('avatar-alex')
        ->assertDontSee('cdn.tailwindcss.com')
        ->assertDontSee('@endsection')
        ->assertDontSee('href="#"', false);
});

it('uses the enquiry route instead of a disabled signup on the public pricing page', function (): void {
    config(['app.system_setting.allow_registration' => false]);

    $this->get(route('product.template'))->assertSuccessful()
        ->assertSee(route('product.enterprise'), false)
        ->assertDontSee('href="'.route('register').'"', false);
});

it('reads the public plan summary prices and included editors from the billing catalogue', function (): void {
    config([
        'usage-pricing.hybrid.website_plans.essentials.GHS' => 345,
        'usage-pricing.hybrid.website_plans.essentials.setup.GHS' => 700,
        'usage-pricing.hybrid.website_plans.essentials.editors' => 4,
    ]);

    $this->get(route('product.template'))->assertSuccessful()
        ->assertSee('GHS 345', false)
        ->assertSee('One-time standard setup: GHS 700', false)
        ->assertSee('4 website editors');
});
