<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enum\UsageMetric;
use App\Models\Package;
use App\Models\UsagePrice;
use Illuminate\Database\Seeder;

final class RecommendedUsagePriceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('usage-pricing.meters', []) as $metric => $rates) {
            foreach (['USD', 'GHS'] as $currency) {
                UsagePrice::query()->firstOrCreate([
                    'target_type' => null, 'target_id' => null,
                    'metric' => $metric, 'currency' => $currency,
                ], ['unit_price' => $rates[$currency], 'unit_quantity' => $rates['unit_quantity']]);
            }
        }

        foreach (config('usage-pricing.active_users', []) as $slug => $rates) {
            $package = Package::query()->where('slug', $slug)->first();
            if ($package === null) {
                continue;
            }
            foreach (['USD', 'GHS'] as $currency) {
                UsagePrice::query()->firstOrCreate([
                    'target_type' => Package::class, 'target_id' => $package->id,
                    'metric' => UsageMetric::USER_ACTIVE_MONTHLY, 'currency' => $currency,
                ], ['unit_price' => $rates[$currency], 'unit_quantity' => 1]);
            }
        }
    }
}
