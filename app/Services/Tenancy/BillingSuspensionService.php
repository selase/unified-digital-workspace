<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Invoice;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class BillingSuspensionService
{
    /**
     * The first day the tenant's site is temporarily unavailable for this invoice.
     */
    public function suspensionDate(Invoice $invoice): ?CarbonInterface
    {
        if ($invoice->due_at === null) {
            return null;
        }

        return $invoice->due_at->copy()->startOfDay()
            ->addDays((int) config('billing.communications.suspend_after_days_overdue', 14) + 1);
    }

    public function isSuspended(Tenant $tenant): bool
    {
        if (! config('billing.suspension_enabled', true)) {
            return false;
        }

        return Cache::remember('billing-suspended:'.$tenant->id, 60, fn (): bool => $this->outstandingPastSuspension($tenant)->isNotEmpty());
    }

    public function forget(Tenant $tenant): void
    {
        Cache::forget('billing-suspended:'.$tenant->id);
    }

    /**
     * Issued or overdue invoices that have passed their suspension date.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function outstandingPastSuspension(Tenant $tenant): \Illuminate\Support\Collection
    {
        return Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE])
            ->whereNotNull('due_at')
            ->get()
            ->filter(fn (Invoice $invoice): bool => today()->gte($this->suspensionDate($invoice)));
    }
}
