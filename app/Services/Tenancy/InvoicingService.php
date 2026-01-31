<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enum\UsageMetric;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\UsageRollup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InvoicingService
{
    public function __construct(
        private readonly PricingService $pricingService
    ) {}

    /**
     * Generate an invoice for a tenant for a specific period.
     */
    public function generate(Tenant $tenant, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): Invoice
    {
        return DB::connection('landlord')->transaction(function () use ($tenant, $start, $end) {
            // 1. Idempotency check: Already generated for this period?
            $existing = Invoice::where('tenant_id', $tenant->id)
                ->where('period_start', $start)
                ->where('period_end', $end)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($existing) {
                return $existing;
            }

            // 2. Create Invoice Shell
            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'number' => $this->generateInvoiceNumber($start),
                'period_start' => $start,
                'period_end' => $end,
                'due_at' => now()->addDays(14), // Default 14 days due
                'status' => 'draft',
                'currency' => 'USD', // Default
            ]);

            $subtotal = 0.0;

            // 3. Add Base Plan Charge
            $basePriceItem = $this->createBasePlanItem($tenant, $invoice);
            if ($basePriceItem) {
                $subtotal += (float) $basePriceItem->subtotal;
            }

            // 4. Collect Metered Usage
            $meteredItems = $this->createMeteredUsageItems($tenant, $invoice, $start, $end);
            foreach ($meteredItems as $item) {
                $subtotal += (float) $item->subtotal;
            }

            // 5. Calculate Taxes
            $taxCalc = Tax::calculateFor($subtotal);
            
            // 6. Update Invoice Totals
            $invoice->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxCalc['total_tax'],
                'total' => $subtotal + $taxCalc['total_tax'],
                'tax_details' => $taxCalc['taxes'],
            ]);

            return $invoice;
        });
    }

    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber(\Carbon\CarbonInterface $date): string
    {
        $prefix = 'INV-' . $date->format('Ym');
        $random = strtoupper(Str::random(4));
        return "{$prefix}-{$random}";
    }

    /**
     * Create the base plan charge item.
     */
    private function createBasePlanItem(Tenant $tenant, Invoice $invoice): ?InvoiceItem
    {
        if (! $tenant->package) {
            return null;
        }

        $package = $tenant->package;
        $description = "{$package->name} - Base Plan (" . ucfirst($package->interval) . ")";
        $quantity = 1.0;
        $unitPrice = (float) $package->price;

        // If per-seat billing, quantity is user count
        if ($package->billing_model === \App\Models\Package::BILLING_MODEL_PER_SEAT) {
            $quantity = (float) $tenant->users()->count();
            $description = "{$package->name} - Per Seat License (" . (int)$quantity . " users)";
        }

        $subtotal = $quantity * $unitPrice;

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'meta' => ['type' => 'base_plan', 'package_id' => $package->id],
        ]);
    }

    /**
     * Aggregate rollups and create line items.
     */
    private function createMeteredUsageItems(Tenant $tenant, Invoice $invoice, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        $items = [];

        // Sum up all Daily rollups for this tenant in the period
        $rollups = UsageRollup::where('tenant_id', $tenant->id)
            ->where('period', 'day')
            ->whereBetween('period_start', [$start, $end])
            ->select('metric', DB::raw('SUM(value) as total_value'))
            ->groupBy('metric')
            ->get();

        foreach ($rollups as $rollup) {
            /** @var UsageMetric $metric */
            $metric = $rollup->metric;
            $quantity = (float) $rollup->total_value;

            if ($quantity <= 0) continue;

            $unitPriceObj = $this->pricingService->getUnitPrice($tenant, $metric);
            $cost = $this->pricingService->calculateCost($tenant, $metric, $quantity);

            if ($cost <= 0 && (! $unitPriceObj || $unitPriceObj->unit_price <= 0)) {
                continue; // Skip free/unpriced metrics
            }

            $items[] = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'metric' => $metric,
                'description' => "Metered Usage: " . $this->getMetricLabel($metric),
                'quantity' => $quantity,
                'unit_price' => $unitPriceObj ? $unitPriceObj->unit_price : 0,
                'subtotal' => $cost,
                'meta' => [
                    'type' => 'metered',
                    'unit_quantity' => $unitPriceObj ? $unitPriceObj->unit_quantity : 1,
                    'markup_applied' => $this->pricingService->getEffectiveMarkup($tenant)
                ],
            ]);
        }

        return $items;
    }

    private function getMetricLabel(UsageMetric $metric): string
    {
        return match($metric) {
            UsageMetric::REQUEST_COUNT => 'HTTP Requests',
            UsageMetric::JOB_COUNT => 'Background Jobs',
            UsageMetric::STORAGE_BYTES => 'Cloud Storage (Average Bytes)',
            UsageMetric::DB_BYTES => 'Database Footprint (Average Bytes)',
            UsageMetric::USER_ACTIVE_DAILY => 'Active Users (Total Activity)',
            default => $metric->value,
        };
    }
}
