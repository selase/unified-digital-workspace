<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinInvoice;
use App\Modules\ProjectManagement\Models\Project;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the hours people logged against a project's tasks into a draft invoice. Each time entry can be billed once:
 * entries on an invoice that is later voided are released so they can be billed again.
 */
final class TimeBillingService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Unbilled time for a project between two dates, one row per task.
     *
     * @return Collection<int, object{task_id: int, title: string, minutes: int, entries: int}>
     */
    public function unbilled(string $tenantId, Project $project, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->unbilledQuery($tenantId, $project, $from, $to)
            ->groupBy('t.id', 't.title')->orderBy('t.title')
            ->selectRaw('t.id as task_id, t.title as title, SUM(e.minutes) as minutes, COUNT(*) as entries')->get()
            ->map(function (object $row): object {
                $row->minutes = (int) $row->minutes;
                $row->entries = (int) $row->entries;

                return $row;
            });
    }

    /**
     * Creates a draft invoice with a line per task (hours at the hourly rate) and marks those entries as billed.
     *
     * @param  int  $hourlyRate  minor units per hour
     */
    public function createDraftInvoice(string $tenantId, FinContact $contact, Project $project, CarbonInterface $from, CarbonInterface $to, int $hourlyRate, ?int $accountId = null, ?string $userId = null): FinInvoice
    {
        if ($hourlyRate <= 0) {
            throw new DomainException('Enter an hourly rate greater than zero.');
        }

        return DB::connection('tenant')->transaction(function () use ($tenantId, $contact, $project, $from, $to, $hourlyRate, $accountId, $userId): FinInvoice {
            $entries = $this->unbilledQuery($tenantId, $project, $from, $to)->lockForUpdate()->get(['e.id as id', 'e.minutes as minutes', 't.id as task_id', 't.title as title']);
            if ($entries->isEmpty()) {
                throw new DomainException('There is no unbilled time for this project in that period.');
            }

            $lines = $entries->groupBy('task_id')->map(fn (Collection $group): array => [
                'description' => $project->name.': '.$group->first()->title.' ('.number_format($group->sum('minutes') / 60, 2).' h)',
                'quantity' => round($group->sum('minutes') / 60, 3),
                'unit_price' => $hourlyRate,
                'account_id' => $accountId,
            ])->values()->all();

            $invoice = $this->invoices->saveDraft($tenantId, $contact, [
                'issue_date' => today()->toDateString(),
                'notes' => 'Time worked on '.$project->name.', '.$from->toDateString().' to '.$to->toDateString().'.',
            ], $lines, null, $userId);

            DB::connection('tenant')->table('fin_time_billing')->insert($entries->map(fn (object $entry): array => [
                'tenant_id' => $tenantId, 'time_entry_id' => $entry->id, 'invoice_id' => $invoice->id, 'created_at' => now(), 'updated_at' => now(),
            ])->all());

            return $invoice;
        });
    }

    private function unbilledQuery(string $tenantId, Project $project, CarbonInterface $from, CarbonInterface $to): \Illuminate\Database\Query\Builder
    {
        return DB::connection('tenant')->table('time_entries as e')
            ->join('tasks as t', 't.id', '=', 'e.task_id')
            ->where('e.tenant_id', $tenantId)->where('t.project_id', $project->id)
            ->whereDate('e.entry_date', '>=', $from->toDateString())->whereDate('e.entry_date', '<=', $to->toDateString())
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))->from('fin_time_billing as b')->join('fin_invoices as i', 'i.id', '=', 'b.invoice_id')
                    ->whereColumn('b.time_entry_id', 'e.id')->where('i.status', '!=', FinInvoice::STATUS_VOID);
            });
    }
}
