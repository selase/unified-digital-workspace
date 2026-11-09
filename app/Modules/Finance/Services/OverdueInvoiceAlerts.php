<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\Finance\Support\ActivateTenant;
use App\Modules\Finance\Support\Money;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use Carbon\CarbonInterface;
use Throwable;

/** Raises one alert per overdue customer invoice. The alert system never sends the same occurrence twice. */
final class OverdueInvoiceAlerts
{
    public function __construct(private readonly AlertDispatcher $alerts) {}

    /** Returns how many overdue invoices were checked across all tenants with Finance switched on. */
    public function run(?CarbonInterface $today = null): int
    {
        $today ??= today();
        $checked = 0;

        $tenantIds = TenantModule::query()->where('module_slug', 'finance')->where('is_enabled', true)->pluck('tenant_id');

        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            try {
                ActivateTenant::for($tenant);
            } catch (Throwable $error) {
                report($error);

                continue;
            }

            $overdue = FinInvoice::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', FinInvoice::TYPE_INVOICE)
                ->whereIn('status', [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID])
                ->whereDate('due_date', '<', $today->toDateString())
                ->with('contact')
                ->get();

            foreach ($overdue as $invoice) {
                $checked++;
                $this->alerts->dispatchSafely(
                    $tenant,
                    AlertEvent::FINANCE_INVOICE_OVERDUE,
                    'Invoice overdue',
                    'Invoice '.$invoice->number.' for '.$invoice->contact->name.' ('.Money::format($invoice->balanceDue(), $invoice->currency).' outstanding) was due on '.$invoice->due_date->toDateString().'.',
                    'finance-overdue-'.$invoice->id,
                    route('finance.invoices.show', $invoice->uuid, false),
                );
            }
        }

        return $checked;
    }
}
