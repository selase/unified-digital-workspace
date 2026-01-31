<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

final class PricingTable extends Component
{
    public $billingCycle = 'monthly';

    public $plans = [];

    public function mount($plans = [])
    {
        $this->plans = $plans ?: config('product-page.plans', []);
    }

    public function setBillingCycle($cycle)
    {
        $this->billingCycle = $cycle;
    }

    public function render()
    {
        return view('livewire.pricing-table');
    }
}
