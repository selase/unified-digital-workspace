<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

/**
 * Optional Ghana standard rates effective 1 January 2026 (Act 1151).
 * https://gra.gov.gh/domestic-tax/tax-types/vat/
 * https://gra.gov.gh/news/portfolio/notice-to-all-vat-registered-taxpayers/
 *
 * Not registered in DatabaseSeeder. Rules remain disabled until the supplier's
 * VAT registration and commercial rates are approved. Before opting a supply
 * into country taxes, determine whether it is taxable, exempt or zero-rated.
 * All three rates use the same net base; the COVID levy has been abolished.
 * Existing rules and administrator overrides are never rewritten.
 */
final class GhanaTaxSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['NHIL', 2.5], ['GETFund Levy', 2.5], ['VAT', 15]] as $index => [$name, $rate]) {
            Tax::query()->firstOrCreate(
                ['country_code' => 'GH', 'name' => $name],
                ['rate' => $rate, 'priority' => $index + 1, 'is_compound' => false, 'is_active' => false],
            );
        }
    }
}
