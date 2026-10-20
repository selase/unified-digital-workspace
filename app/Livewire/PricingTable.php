<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Tenancy\HybridPricingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PricingTable extends Component
{
    public ?string $quoteCurrency = 'GHS';

    public int|string|null $selfServiceUsers = 0;

    public int|string|null $operationalUsers = 0;

    public ?string $websitePlan = 'essentials';

    public int|string|null $websiteEditors = 1;

    public bool $includeSetup = true;

    public int|float|string|null $fileStorageGiB = 0;

    public int|float|string|null $databaseGiB = 0;

    public int|float|string|null $bandwidthGiB = 0;

    /** @var array<int, string> */
    public array $selectedPackages = [];

    public function render(): View
    {
        $website = [
            'websitePlan' => $this->websitePlan,
            'websiteEditors' => $this->websiteEditors,
            'includeSetup' => $this->includeSetup,
            'fileStorageGiB' => $this->fileStorageGiB,
            'databaseGiB' => $this->databaseGiB,
            'bandwidthGiB' => $this->bandwidthGiB,
        ];
        $validator = HybridPricingService::validator([
            'selfServiceUsers' => $this->selfServiceUsers,
            'operationalUsers' => $this->operationalUsers,
            'selectedPackages' => $this->selectedPackages,
            'quoteCurrency' => $this->quoteCurrency,
            ...$website,
        ]);

        $quote = $validator->fails() ? null : app(HybridPricingService::class)->quote(
            (int) $this->selfServiceUsers,
            (int) $this->operationalUsers,
            $this->selectedPackages,
            (string) $this->quoteCurrency,
            [
                'websitePlan' => (string) $this->websitePlan,
                'websiteEditors' => (int) $this->websiteEditors,
                'includeSetup' => $this->includeSetup,
                'fileStorageGiB' => (float) $this->fileStorageGiB,
                'databaseGiB' => (float) $this->databaseGiB,
                'bandwidthGiB' => (float) $this->bandwidthGiB,
            ],
        );

        return view('livewire.pricing-table', [
            'quote' => $quote,
            'quoteErrors' => $validator->errors()->all(),
            'hybrid' => config('usage-pricing.hybrid'),
        ]);
    }

    public function hospitalExample(): void
    {
        $this->selfServiceUsers = 1350;
        $this->operationalUsers = 150;
        $this->selectedPackages = ['collaboration', 'hr', 'quality', 'facilities'];
        $this->websitePlan = 'essentials';
        $this->websiteEditors = 2;
        $this->includeSetup = false;
        $this->fileStorageGiB = 0;
        $this->databaseGiB = 0;
        $this->bandwidthGiB = 0;
    }
}
