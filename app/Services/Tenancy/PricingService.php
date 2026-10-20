<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enum\UsageMetric;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\UsagePrice;
use App\Models\UsageRollup;
use Carbon\CarbonInterface;

final class PricingService
{
    /**
     * Get the effective unit price for a metric for a specific tenant.
     */
    public function getUnitPrice(Tenant $tenant, UsageMetric $metric, ?string $currency = null): ?UsagePrice
    {
        $currency ??= $tenant->billingCurrency();
        // 1. Check for tenant-specific override
        $price = UsagePrice::where('target_type', Tenant::class)
            ->where('target_id', $tenant->id)
            ->where('currency', $currency)
            ->where('metric', $metric)
            ->first();

        if ($price) {
            return $price;
        }

        // 2. Check for package-level pricing
        if ($tenant->package_id) {
            $price = UsagePrice::where('target_type', Package::class)
                ->where('target_id', $tenant->package_id)
                ->where('currency', $currency)
                ->where('metric', $metric)
                ->first();

            if ($price) {
                return $price;
            }
        }

        // 3. Fallback to Global standard pricing (target_type is null)
        return UsagePrice::whereNull('target_type')
            ->where('currency', $currency)
            ->where('metric', $metric)
            ->first();
    }

    /**
     * Gauge quantities average observed daily totals; counters sum over the period.
     * Monthly active users use durable per-user monthly rollups, never daily counts.
     *
     * @return array<string, float>
     */
    public function billableQuantities(Tenant $tenant, CarbonInterface $start, CarbonInterface $end): array
    {
        $quantities = [];
        $rollups = UsageRollup::where('tenant_id', $tenant->id)
            ->where('period', 'day')
            ->whereBetween('period_start', [$start, $end])
            ->get()
            ->reject(fn (UsageRollup $rollup): bool => $rollup->metric === UsageMetric::EMAIL_COUNT
                && ($rollup->dimensions['billing_mode'] ?? null) === 'prepaid');

        foreach ($rollups->groupBy(fn (UsageRollup $rollup): string => $rollup->metric->value) as $metric => $rows) {
            if ($metric === UsageMetric::USER_ACTIVE_MONTHLY->value || ($metric === UsageMetric::DB_BYTES->value && ! $tenant->requiresDedicatedDb())) {
                continue;
            }
            $quantities[$metric] = in_array($metric, [UsageMetric::STORAGE_BYTES->value, UsageMetric::DB_BYTES->value], true)
                ? (float) $rows->groupBy(fn (UsageRollup $rollup): string => $rollup->period_start->toDateString())
                    ->map(fn ($daily): float => (float) $daily->sum('value'))->avg()
                : (float) $rows->sum('value');
        }

        $quantities[UsageMetric::USER_ACTIVE_MONTHLY->value] = (float) UsageRollup::where('tenant_id', $tenant->id)
            ->where('metric', UsageMetric::USER_ACTIVE_MONTHLY)
            ->where('period', 'month')
            ->whereBetween('period_start', [$start, $end])
            ->sum('value');

        return $quantities;
    }

    /**
     * Get the effective markup percentage for a tenant.
     */
    public function getEffectiveMarkup(Tenant $tenant): float
    {
        // Markups are additive: Global + Package + Tenant override
        $globalMarkup = (float) config('billing.global_markup', 0);
        $packageMarkup = $tenant->package ? (float) $tenant->package->markup_percentage : 0;
        $tenantMarkup = (float) $tenant->markup_percentage;

        return $globalMarkup + $packageMarkup + $tenantMarkup;
    }

    /**
     * Calculate the cost for a given usage quantity.
     */
    public function calculateCost(Tenant $tenant, UsageMetric $metric, float $quantity): float
    {
        $price = $this->getUnitPrice($tenant, $metric);

        if (! $price || $price->unit_price <= 0) {
            return 0.0;
        }

        // Base Cost = (Quantity / Unit Quantity) * Unit Price
        $baseCost = ($quantity / (float) $price->unit_quantity) * (float) $price->unit_price;

        // Apply Markup
        $markupPercent = $this->getEffectiveMarkup($tenant);
        $totalCost = $baseCost * (1 + ($markupPercent / 100));

        return round($totalCost, 6);
    }
}
