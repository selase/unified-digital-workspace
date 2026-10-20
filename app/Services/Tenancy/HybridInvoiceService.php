<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enum\UsageMetric;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\TenantBillingAgreement;
use Carbon\CarbonInterface;
use DomainException;

final class HybridInvoiceService
{
    /**
     * @return list<array{description: string, quantity: float, unit_price: float, subtotal: float, meta: array<string, mixed>}>
     */
    public function charges(Tenant $tenant, TenantBillingAgreement $agreement, CarbonInterface $start, CarbonInterface $end): array
    {
        if ((string) $agreement->tenant_id !== (string) $tenant->id) {
            throw new DomainException('The billing agreement belongs to another organisation.');
        }
        $snapshot = $agreement->snapshot;
        $card = $snapshot['hybrid'];
        $currency = $agreement->currency;
        $plan = $card['website_plans'][$agreement->plan_slug];
        $baseMeta = ['billing_agreement_id' => $agreement->id, 'currency' => $currency];
        $lines = [$this->line($plan['name'].' — monthly website subscription', 1, (float) $plan[$currency], $baseMeta + ['type' => 'website_subscription'])];
        $extra = max(0, $agreement->editor_seats - $plan['editors']);
        if ($extra > 0) {
            $lines[] = $this->line('Additional provisioned website editor seats', $extra, (float) $card['extra_editor'][$currency], $baseMeta + ['type' => 'website_editors']);
        }
        foreach ($snapshot['selected_packages'] as $slug) {
            $package = $card['packages'][$slug];
            $lines[] = $this->line($package['name'].' — monthly module subscription', 1, (float) $package[$currency], $baseMeta + ['type' => 'module_subscription', 'module_package' => $slug]);
        }
        if ($agreement->setup_required && $agreement->effective_from->isSameMonth($start)) {
            $lines[] = $this->line($plan['name'].' — one-time standard setup', 1, (float) $plan['setup'][$currency], $baseMeta + ['type' => 'standard_setup']);
        }
        foreach (app(BillingAccessService::class)->counts($tenant, $start) as $level => $quantity) {
            if ($quantity <= 0) {
                continue;
            }
            $rate = $card['access'][$level];
            $unitCents = (int) round($rate[$currency] * 100);
            $previous = 0;
            $hundredths = 0;
            foreach ($card['bands'] as $band) {
                $upper = $band['up_to'] ?? $quantity;
                $count = max(0, min($quantity, $upper) - $previous);
                $hundredths += $count * $unitCents * (100 - $band['discount_percent']);
                $previous = $upper;
                if ($quantity <= $upper) {
                    break;
                }
            }
            $subtotal = round($hundredths / 100, 0, PHP_ROUND_HALF_UP) / 100;
            $line = $this->line($rate['name'].' — distinct monthly active users', $quantity, (float) $rate[$currency], $baseMeta + [
                'type' => 'active_access', 'access_level' => $level,
                'discount' => round($quantity * $rate[$currency] - $subtotal, 2), 'bands' => $card['bands'],
            ]);
            $line['subtotal'] = $subtotal;
            if ($line['meta']['discount'] > 0) {
                $line['description'] .= ' — '.$currency.' '.number_format($line['meta']['discount'], 2).' graduated discount applied';
            }
            $lines[] = $line;
        }
        $usage = app(PricingService::class)->billableQuantities($tenant, $start, $end);
        foreach ([UsageMetric::EMAIL_COUNT, UsageMetric::DB_BYTES] as $metric) {
            if ($metric === UsageMetric::DB_BYTES && ! ($snapshot['dedicated_db'] ?? false)) {
                continue;
            }
            $quantity = $usage[$metric->value] ?? 0;
            if ($quantity <= 0) {
                continue;
            }
            $rate = $snapshot['meters'][$metric->value] ?? null;
            if (! $rate || ! isset($rate[$currency]) || $rate['unit_quantity'] <= 0) {
                throw new DomainException('The saved agreement has no valid '.$currency.' rate for '.$metric->value.'.');
            }
            $line = $this->line($metric === UsageMetric::EMAIL_COUNT ? 'Postpaid email recipient deliveries' : 'Dedicated database — average observed bytes', $quantity, (float) $rate[$currency], $baseMeta + ['type' => 'metered', 'metric' => $metric->value, 'unit_quantity' => $rate['unit_quantity']]);
            $line['subtotal'] = round(($quantity / $rate['unit_quantity']) * $rate[$currency], 2, PHP_ROUND_HALF_UP);
            $lines[] = $line;
        }

        return $lines;
    }

    public function append(Tenant $tenant, TenantBillingAgreement $agreement, Invoice $invoice): float
    {
        $subtotal = 0.0;
        foreach ($this->charges($tenant, $agreement, $invoice->period_start, $invoice->period_end) as $line) {
            InvoiceItem::query()->create(['invoice_id' => $invoice->id, ...$line]);
            $subtotal += $line['subtotal'];
        }

        return round($subtotal, 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{description: string, quantity: float, unit_price: float, subtotal: float, meta: array<string, mixed>}
     */
    private function line(string $description, float $quantity, float $unitPrice, array $meta): array
    {
        return ['description' => $description, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'subtotal' => round($quantity * $unitPrice, 2, PHP_ROUND_HALF_UP), 'meta' => $meta];
    }
}
