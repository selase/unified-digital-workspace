<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBill;
use App\Modules\Finance\Models\FinInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FinanceReports
{
    /**
     * Debit and credit totals per account. The two grand totals are always equal.
     *
     * @return array{rows: Collection<int, array{account: FinAccount, debit: int, credit: int}>, debit: int, credit: int}
     */
    public function trialBalance(string $tenantId, ?string $asOf = null): array
    {
        $totals = DB::connection('tenant')->table('fin_journal_lines as l')
            ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('j.tenant_id', $tenantId)
            ->when($asOf, fn ($query) => $query->whereDate('j.date', '<=', $asOf))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get()
            ->keyBy('account_id');

        $rows = FinAccount::query()->where('tenant_id', $tenantId)->orderBy('code')->get()
            ->map(function (FinAccount $account) use ($totals): array {
                $row = $totals->get($account->id);
                $net = (int) ($row->debit ?? 0) - (int) ($row->credit ?? 0);

                return ['account' => $account, 'debit' => max($net, 0), 'credit' => max(-$net, 0)];
            })
            ->filter(fn (array $row): bool => $row['debit'] > 0 || $row['credit'] > 0)
            ->values();

        return ['rows' => $rows, 'debit' => (int) $rows->sum('debit'), 'credit' => (int) $rows->sum('credit')];
    }

    /**
     * Open invoices grouped by how overdue they are.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function agedReceivables(string $tenantId): array
    {
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];

        $invoices = FinInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('type', FinInvoice::TYPE_INVOICE)
            ->whereIn('status', [FinInvoice::STATUS_SENT, FinInvoice::STATUS_PARTLY_PAID])
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $rows = $invoices->map(function (FinInvoice $invoice) use (&$buckets): array {
            $daysLate = $invoice->due_date !== null ? (int) $invoice->due_date->diffInDays(today(), false) : 0;
            $bucket = match (true) {
                $daysLate <= 0 => 'current',
                $daysLate <= 30 => '1_30',
                $daysLate <= 60 => '31_60',
                $daysLate <= 90 => '61_90',
                default => '90_plus',
            };
            $buckets[$bucket] += $invoice->baseBalanceDue();

            return ['invoice' => $invoice, 'bucket' => $bucket, 'days_late' => max($daysLate, 0), 'balance' => $invoice->baseBalanceDue()];
        });

        return ['rows' => $rows, 'totals' => $buckets + ['total' => array_sum($buckets)]];
    }

    /**
     * Open bills grouped by how overdue they are.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function agedPayables(string $tenantId): array
    {
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];

        $bills = FinBill::query()
            ->where('tenant_id', $tenantId)
            ->where('type', FinBill::TYPE_BILL)
            ->whereIn('status', [FinBill::STATUS_APPROVED, FinBill::STATUS_PARTLY_PAID])
            ->with('contact')
            ->orderBy('due_date')
            ->get();

        $rows = $bills->map(function (FinBill $bill) use (&$buckets): array {
            $daysLate = $bill->due_date !== null ? (int) $bill->due_date->diffInDays(today(), false) : 0;
            $bucket = match (true) {
                $daysLate <= 0 => 'current',
                $daysLate <= 30 => '1_30',
                $daysLate <= 60 => '31_60',
                $daysLate <= 90 => '61_90',
                default => '90_plus',
            };
            $buckets[$bucket] += $bill->balanceDue();

            return ['bill' => $bill, 'bucket' => $bucket, 'days_late' => max($daysLate, 0), 'balance' => $bill->balanceDue()];
        });

        return ['rows' => $rows, 'totals' => $buckets + ['total' => array_sum($buckets)]];
    }

    /**
     * @return array{receivables: int, overdue: int, income_month: int, expense_month: int, cash: int}
     */
    public function dashboard(string $tenantId): array
    {
        $aged = $this->agedReceivables($tenantId);
        $monthStart = today()->startOfMonth()->toDateString();

        $sumByType = fn (string $type): int => (int) DB::connection('tenant')->table('fin_journal_lines as l')
            ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
            ->join('fin_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.tenant_id', $tenantId)->where('a.type', $type)
            ->where(fn ($inner) => $inner->whereNull('j.source_type')->orWhere('j.source_type', '!=', YearEndClose::SOURCE))
            ->whereDate('j.date', '>=', $monthStart)
            ->selectRaw($type === 'income' ? 'COALESCE(SUM(l.credit - l.debit), 0) as total' : 'COALESCE(SUM(l.debit - l.credit), 0) as total')
            ->value('total');

        $cash = (int) DB::connection('tenant')->table('fin_journal_lines as l')
            ->join('fin_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('a.tenant_id', $tenantId)
            ->whereIn('a.system_key', [ChartOfAccounts::BANK, ChartOfAccounts::GATEWAY_CLEARING])
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as total')
            ->value('total');

        return [
            'receivables' => $aged['totals']['total'],
            'overdue' => $aged['totals']['total'] - $aged['totals']['current'],
            'income_month' => $sumByType('income'),
            'expense_month' => $sumByType('expense'),
            'cash' => $cash,
        ];
    }
}
