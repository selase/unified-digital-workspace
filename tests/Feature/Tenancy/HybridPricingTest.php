<?php

declare(strict_types=1);

use App\Services\Tenancy\HybridPricingService;
use Illuminate\Validation\ValidationException;

it('quotes a hospital without charging every employee as an operator', function (): void {
    $quote = app(HybridPricingService::class)->quote(1350, 150, ['collaboration', 'hr', 'quality', 'facilities'], 'GHS');
    expect($quote['base_fee'])->toBe(300.0)
        ->and($quote['access_before_discount'])->toBe(10500.0)
        ->and($quote['access_discount'])->toBe(1175.0)
        ->and($quote['access_total'])->toBe(9325.0)
        ->and($quote['packages_total'])->toBe(3000.0)
        ->and($quote['total'])->toBe(12625.0)
        ->and($quote['active_users'])->toBe(1500);
});

it('quotes independent dollar rates', function (): void {
    $quote = app(HybridPricingService::class)->quote(1350, 150, ['collaboration', 'hr', 'quality', 'facilities'], 'USD');
    expect($quote['access_total'])->toBe(860.0)
        ->and($quote['packages_total'])->toBe(240.0)
        ->and($quote['total'])->toBe(1125.0);
});

it('offers commerce without institution modules or duplicate package charges', function (): void {
    $quote = app(HybridPricingService::class)->quote(0, 5, ['commerce', 'commerce'], 'GHS');
    expect($quote['modules'])->toContain('store', 'vendor-marketplace')
        ->not->toContain('hrms-core', 'quality-monitoring', 'real-estate')
        ->and($quote['packages_total'])->toBe(750.0)
        ->and($quote['total'])->toBe(1175.0)
        ->and($quote['package_lines'])->toHaveCount(1);
});

it('keeps additional user costs positive across discount boundaries', function (int $boundary): void {
    $service = app(HybridPricingService::class);
    expect($service->quote($boundary + 1, 0, [], 'GHS')['total'])
        ->toBeGreaterThan($service->quote($boundary, 0, [], 'GHS')['total']);
})->with([99, 100, 499, 500, 1499, 1500, 1501]);

it('keeps module subscriptions payable when nobody logs in', function (): void {
    $quote = app(HybridPricingService::class)->quote(0, 0, ['hr'], 'GHS');
    expect($quote['access_total'])->toBe(0.0)->and($quote['total'])->toBe(1300.0);
});

it('charges for websites blogs and forms even without staff logins', function (): void {
    expect(app(HybridPricingService::class)->quote(0, 0, [], 'GHS')['total'])->toBe(300.0);
    expect(app(HybridPricingService::class)->quote(0, 0, [], 'USD')['total'])->toBe(25.0);
});

it('rejects invalid quotes', function (int $selfService, int $operators, array $packages, string $currency): void {
    app(HybridPricingService::class)->quote($selfService, $operators, $packages, $currency);
})->with([
    [-1, 0, [], 'GHS'], [0, -1, [], 'GHS'], [100001, 0, [], 'GHS'],
    [0, 0, ['unpublished-ai'], 'GHS'], [0, 0, [], 'EUR'],
])->throws(ValidationException::class);

it('leaves cumulative package grants and invoice rates unchanged', function (): void {
    $plans = config('packages.plans');
    app(HybridPricingService::class)->quote(1350, 150, ['hr'], 'GHS');
    expect(config('packages.plans'))->toBe($plans)
        ->and(config('usage-pricing.active_users.institution.GHS'))->toBe(50)
        ->and(config('usage-pricing.hybrid.status'))->toBe('opt_in');
});

it('prices every proposed capability and covers every currently catalogued module', function (): void {
    $service = app(HybridPricingService::class);
    $slugs = array_keys(config('usage-pricing.hybrid.packages'));
    $quote = $service->quote(0, 0, $slugs, 'GHS');
    $existingModules = array_unique(array_merge(...array_column(config('packages.plans'), 'modules')));
    foreach ($existingModules as $module) {
        expect($quote['modules'])->toContain($module);
    }
    foreach (['GHS', 'USD'] as $currency) {
        expect(config('usage-pricing.hybrid.website_plans.essentials.'.$currency))->toBeGreaterThan(0);
        foreach (config('usage-pricing.hybrid.packages') as $package) {
            expect($package[$currency])->toBeGreaterThan(0);
        }
    }
});

it('includes website editors without operational seat charges', function (string $plan, int $editors, float $ghs, float $usd): void {
    foreach (['GHS' => $ghs, 'USD' => $usd] as $currency => $expected) {
        $quote = app(HybridPricingService::class)->quote(0, 0, [], $currency, ['websitePlan' => $plan, 'websiteEditors' => $editors]);
        expect($quote['total'])->toBe($expected)
            ->and($quote['extra_editor_total'])->toBe(0.0)
            ->and($quote['features'])->toContain('custom-domains');
    }
})->with([['essentials', 2, 300.0, 25.0], ['managed_business', 5, 600.0, 50.0]]);

it('separates one-time setup from recurring subscription charges', function (): void {
    $quote = app(HybridPricingService::class)->quote(0, 0, [], 'GHS', ['websitePlan' => 'managed_business', 'includeSetup' => true]);
    expect($quote['total'])->toBe(600.0)->and($quote['setup_fee'])->toBe(1000.0)->and($quote['first_month_total'])->toBe(1600.0);
    expect(app(HybridPricingService::class)->quote(0, 0, [], 'USD', ['includeSetup' => true])['first_month_total'])->toBe(75.0);
    expect(app(HybridPricingService::class)->quote(0, 0, [], 'GHS')['setup_fee'])->toBe(0.0);
});

it('charges only for website editors beyond the included allowance', function (): void {
    $quote = app(HybridPricingService::class)->quote(0, 0, [], 'GHS', ['websiteEditors' => 3]);
    expect($quote['extra_editors'])->toBe(1)->and($quote['extra_editor_total'])->toBe(25.0)->and($quote['total'])->toBe(325.0);
});

it('accepts website usage at each allowance boundary', function (string $plan, float $files, float $database, float $bandwidth): void {
    $quote = app(HybridPricingService::class)->quote(0, 0, [], 'GHS', ['websitePlan' => $plan, 'fileStorageGiB' => $files, 'databaseGiB' => $database, 'bandwidthGiB' => $bandwidth]);
    expect($quote['total'])->toBe($plan === 'essentials' ? 300.0 : 600.0);
})->with([['essentials', 5.0, 0.5, 25.0], ['managed_business', 20.0, 2.0, 100.0]]);

it('requires an upgrade or capacity quote above website limits', function (array $options): void {
    app(HybridPricingService::class)->quote(0, 0, [], 'GHS', $options);
})->with([
    [['fileStorageGiB' => 5.01]], [['databaseGiB' => 0.51]], [['bandwidthGiB' => 25.01]],
    [['websitePlan' => 'managed_business', 'fileStorageGiB' => 20.01]],
    [['websitePlan' => 'managed_business', 'databaseGiB' => 2.01]],
    [['websitePlan' => 'managed_business', 'bandwidthGiB' => 100.01]],
    [['websitePlan' => 'free']], [['websiteEditors' => -1]], [['fileStorageGiB' => -1]],
])->throws(ValidationException::class);
