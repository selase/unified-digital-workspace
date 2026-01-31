<?php

declare(strict_types=1);

use App\Livewire\PricingTable;
use Livewire\Livewire;

it('can toggle billing cycle', function () {
    Livewire::test(PricingTable::class)
        ->assertSet('billingCycle', 'monthly')
        ->call('setBillingCycle', 'yearly')
        ->assertSet('billingCycle', 'yearly')
        ->call('setBillingCycle', 'monthly')
        ->assertSet('billingCycle', 'monthly');
});

it('calculates yearly discount prices correctly', function () {
    $plans = [
        [
            'name' => 'Starter',
            'description' => 'Test desc',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'most_popular' => false,
            'features' => [],
            'cta' => ['label' => 'Buy', 'href' => '#'],
        ],
    ];

    Livewire::test(PricingTable::class, ['plans' => $plans])
        ->assertSet('billingCycle', 'monthly')
        ->assertSee('10')
        ->call('setBillingCycle', 'yearly')
        ->assertSee('100');
});

it('highlights the most popular plan', function () {
    $plans = [
        [
            'name' => 'Starter',
            'description' => 'Test desc',
            'monthly_price' => 10,
            'yearly_price' => 100,
            'most_popular' => false,
            'features' => [],
            'cta' => ['label' => 'Buy', 'href' => '#'],
        ],
        [
            'name' => 'Pro',
            'description' => 'Test desc',
            'monthly_price' => 20,
            'yearly_price' => 200,
            'most_popular' => true,
            'features' => [],
            'cta' => ['label' => 'Buy', 'href' => '#'],
        ],
    ];

    Livewire::test(PricingTable::class, ['plans' => $plans])
        ->assertSee('Pro')
        ->assertSeeHtml('data-most-popular="true"');
});
