<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PricingTable extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $plans = [];

    /** @param array<int, array<string, mixed>> $plans */
    public function mount(array $plans = []): void
    {
        $this->plans = $plans ?: config('product-page.plans', []);
    }

    public function render(): View
    {
        return view('livewire.pricing-table');
    }
}
