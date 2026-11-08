<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Modules\Finance\Models\FinRecurringInvoice;
use App\Modules\Finance\Support\ActivateTenant;
use Carbon\CarbonInterface;
use Throwable;

final class RecurringInvoiceService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Creates the next invoice from the template (sent straight away when auto-send is on),
     * then moves the schedule forward. Returns the number of invoices created.
     */
    public function runDue(?CarbonInterface $today = null): int
    {
        $today ??= today();
        $created = 0;

        $tenantIds = TenantModule::query()
            ->where('module_slug', 'finance')
            ->where('is_enabled', true)
            ->pluck('tenant_id');

        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            try {
                // Each tenant may live in its own database, so point at it before reading its templates.
                ActivateTenant::for($tenant);
            } catch (Throwable $error) {
                report($error);

                continue;
            }

            $due = FinRecurringInvoice::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->whereDate('next_run_date', '<=', $today->toDateString())
                ->with('contact')
                ->get();

            foreach ($due as $template) {
                try {
                    $this->generate($template, $today);
                    $created++;
                } catch (Throwable $error) {
                    report($error);
                }
            }
        }

        return $created;
    }

    public function generate(FinRecurringInvoice $template, CarbonInterface $today): void
    {
        $invoice = $this->invoices->saveDraft(
            $template->tenant_id,
            $template->contact,
            [
                'type' => 'invoice',
                'issue_date' => $template->next_run_date->toDateString(),
                'due_date' => $template->next_run_date->copy()->addDays($template->terms_days)->toDateString(),
                'notes' => $template->notes,
                'recurring_id' => $template->id,
            ],
            $template->lines,
        );

        if ($template->auto_send) {
            $this->invoices->send($invoice);
        }

        $next = $this->advance($template);
        $template->forceFill([
            'last_run_at' => now(),
            'next_run_date' => $next->toDateString(),
            'is_active' => $template->end_date === null || $next->lte($template->end_date),
        ])->save();
    }

    private function advance(FinRecurringInvoice $template): CarbonInterface
    {
        $date = $template->next_run_date->copy();

        return match ($template->frequency) {
            'weekly' => $date->addWeek(),
            'quarterly' => $date->addMonthsNoOverflow(3),
            'yearly' => $date->addYearNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    }
}
