<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\TenantDiscount;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class DiscountService
{
    /**
     * Adds a negative line to the invoice for every discount active in the period and
     * returns the total (a negative number). Percentages apply to the gross charge, and
     * the combined discount can never take an invoice below zero.
     */
    public function apply(Tenant $tenant, Invoice $invoice, CarbonInterface $start, CarbonInterface $end, float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        $discounts = TenantDiscount::query()
            ->where('tenant_id', $tenant->id)
            ->overlapping(Carbon::instance($start), Carbon::instance($end))
            ->orderBy('created_at')
            ->get();

        $remaining = $gross;
        $total = 0.0;

        foreach ($discounts as $discount) {
            if ($discount->type === TenantDiscount::TYPE_FIXED && $discount->currency !== $invoice->currency) {
                continue;
            }

            $amount = $discount->type === TenantDiscount::TYPE_PERCENT
                ? round($gross * (float) $discount->value / 100, 2)
                : round((float) $discount->value, 2);
            $amount = min($amount, $remaining);

            if ($amount <= 0) {
                continue;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Discount: '.$discount->name.' ('.$discount->label().')',
                'quantity' => 1,
                'unit_price' => -$amount,
                'subtotal' => -$amount,
                'meta' => ['type' => 'discount', 'discount_id' => $discount->id, 'reason' => $discount->reason],
            ]);

            $remaining -= $amount;
            $total -= $amount;
        }

        return $total;
    }
}
