<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Store\Models\PlatformCreditPackage;
use Illuminate\Database\Seeder;

final class RecommendedPlatformCreditPackageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['GHS' => 1500, 'USD' => 100] as $currency => $priceCents) {
            PlatformCreditPackage::query()->firstOrCreate([
                'credit_type' => 'email',
                'amount_credits' => 1000,
                'currency' => $currency,
            ], [
                'name' => '1,000 Email Credits',
                'price_cents' => $priceCents,
                'is_active' => true,
            ]);
        }
    }
}
