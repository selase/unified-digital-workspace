<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enum\UsageMetric;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tax;
use App\Models\Tenant;
use DomainException;
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
            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $agreement = app(BillingAgreementService::class)->activeFor($tenant, $start);
            if ($agreement) {
                if (! $start->equalTo($start->copy()->startOfMonth()) || $end->toDateString() !== $start->copy()->endOfMonth()->toDateString() || $end->copy()->endOfDay()->isFuture()) {
                    throw new DomainException('Hybrid invoices require a complete, finished calendar month. Use the current-month estimate for unfinished periods.');
                }
                $end = $end->copy()->endOfDay();
            } elseif (app(BillingAgreementService::class)->activeFor($tenant, $end)) {
                throw new DomainException('An invoice cannot mix legacy and hybrid billing periods.');
            }

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
                'currency' => $agreement !== null ? $agreement->currency : $tenant->billingCurrency(),
                'meta' => $agreement ? ['billing_agreement_id' => $agreement->id, 'billing_model' => 'hybrid'] : null,
            ]);

            $subtotal = 0.0;

            // 3. Add Base Plan Charge
            $basePriceItem = $agreement ? null : $this->createBasePlanItem($tenant, $invoice);
            if ($basePriceItem) {
                $subtotal += (float) $basePriceItem->subtotal;
            }

            // 4. Collect Metered Usage
            $meteredItems = $agreement ? [] : $this->createMeteredUsageItems($tenant, $invoice, $start, $end);
            foreach ($meteredItems as $item) {
                $subtotal += (float) $item->subtotal;
            }

            if ($agreement) {
                $subtotal += app(HybridInvoiceService::class)->append($tenant, $agreement, $invoice);
            }

            // 5. Calculate Taxes
            $taxCalc = Tax::calculateForTenant($subtotal, $tenant);

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
        $prefix = 'INV-'.$date->format('Ym');
        $random = mb_strtoupper(Str::random(4));

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
        $description = "{$package->name} - Base Plan (".ucfirst($package->interval).')';
        $quantity = 1.0;
        $unitPrice = $package->priceForCurrency($invoice->currency);

        // If per-seat billing, quantity is user count
        if ($package->billing_model === \App\Models\Package::BILLING_MODEL_PER_SEAT) {
            $quantity = (float) $tenant->users()->count();
            $description = "{$package->name} - Per Seat License (".(int) $quantity.' users)';
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

        foreach ($this->pricingService->billableQuantities($tenant, $start, $end) as $metricValue => $quantity) {
            $metric = UsageMetric::from($metricValue);

            if ($quantity <= 0) {
                continue;
            }

            $unitPriceObj = $this->pricingService->getUnitPrice($tenant, $metric, $invoice->currency, $start);
            if (! $unitPriceObj && $invoice->currency === 'GHS' && $this->pricingService->getUnitPrice($tenant, $metric, 'USD', $start)) {
                throw new DomainException("No GHS rate configured for {$metric->value}; invoice generation stopped.");
            }

            $cost = $this->pricingService->calculateCost($tenant, $metric, $quantity, $start);

            if ($cost <= 0 && (! $unitPriceObj || $unitPriceObj->unit_price <= 0)) {
                continue; // Skip free/unpriced metrics
            }

            $items[] = InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'metric' => $metric,
                'description' => 'Metered Usage: '.$this->getMetricLabel($metric),
                'quantity' => $quantity,
                'unit_price' => $unitPriceObj ? $unitPriceObj->unit_price : 0,
                'subtotal' => $cost,
                'meta' => [
                    'type' => 'metered',
                    'unit_quantity' => $unitPriceObj ? $unitPriceObj->unit_quantity : 1,
                    'markup_applied' => $this->pricingService->getEffectiveMarkup($tenant, $start),
                ],
            ]);
        }

        return $items;
    }

    private function getMetricLabel(UsageMetric $metric): string
    {
        return match ($metric) {
            UsageMetric::REQUEST_COUNT => 'HTTP Requests',
            UsageMetric::JOB_COUNT => 'Background Jobs',
            UsageMetric::STORAGE_BYTES => 'Cloud Storage (Average Bytes)',
            UsageMetric::DB_BYTES => 'Database Footprint (Average Bytes)',
            UsageMetric::USER_ACTIVE_DAILY => 'Daily Active User Activity',
            UsageMetric::USER_ACTIVE_MONTHLY => 'Monthly Active Users (Distinct)',
            default => $metric->value,
        };
    }
}
