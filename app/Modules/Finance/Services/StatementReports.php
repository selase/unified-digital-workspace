<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBankAccount;
use App\Modules\Finance\Models\FinFund;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial statements read straight from posted journal lines: profit and loss,
 * balance sheet, cash flow and the fund report. Nothing here writes to the books.
 */
final class StatementReports
{
    /** Chart codes that count as investing and financing for the cash flow statement. */
    private const INVESTING_PREFIXES = ['15'];

    private const FINANCING_PREFIXES = ['25'];

    public function __construct(private readonly FinanceSettings $settings) {}

    public function fiscalYearStart(string $tenantId, CarbonInterface $date): CarbonImmutable
    {
        $month = max(1, min(12, (int) $this->settings->for($tenantId)->fiscal_year_start_month));
        $start = CarbonImmutable::create($date->year, $month, 1)->startOfDay();

        return $start->gt($date) ? $start->subYear() : $start;
    }

    /**
     * Income less expenses between two dates, optionally for one fund.
     *
     * @return array{income: Collection<int, array{account: FinAccount, amount: int}>, expenses: Collection<int, array{account: FinAccount, amount: int}>, total_income: int, total_expenses: int, net_profit: int}
     */
    public function profitAndLoss(string $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $fundId = null): array
    {
        $totals = $this->lines($tenantId)
            ->whereIn('a.type', ['income', 'expense'])
            ->whereDate('j.date', '>=', $from->toDateString())
            ->whereDate('j.date', '<=', $to->toDateString())
            ->when($fundId !== null, fn (Builder $query) => $query->where('l.fund_id', $fundId))
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get()->keyBy('account_id');

        $build = function (string $type) use ($tenantId, $totals): Collection {
            return FinAccount::query()->where('tenant_id', $tenantId)->where('type', $type)->orderBy('code')->get()
                ->map(function (FinAccount $account) use ($totals, $type): array {
                    $row = $totals->get($account->id);
                    $net = (int) ($row->debit ?? 0) - (int) ($row->credit ?? 0);

                    return ['account' => $account, 'amount' => $type === 'income' ? -$net : $net];
                })
                ->filter(fn (array $row): bool => $row['amount'] !== 0)
                ->values();
        };

        $income = $build('income');
        $expenses = $build('expense');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'total_income' => (int) $income->sum('amount'),
            'total_expenses' => (int) $expenses->sum('amount'),
            'net_profit' => (int) $income->sum('amount') - (int) $expenses->sum('amount'),
        ];
    }

    /**
     * Assets, liabilities and equity at a date. Profit not yet moved into equity is shown as its own lines,
     * so assets always equal liabilities plus equity.
     *
     * @return array{assets: Collection<int, array{account: FinAccount, amount: int}>, liabilities: Collection<int, array{account: FinAccount, amount: int}>, equity: Collection<int, array{account: FinAccount, amount: int}>, current_year_profit: int, prior_years_profit: int, total_assets: int, total_liabilities: int, total_equity: int, balanced: bool}
     */
    public function balanceSheet(string $tenantId, CarbonInterface $asOf): array
    {
        $totals = $this->lines($tenantId)
            ->whereDate('j.date', '<=', $asOf->toDateString())
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get()->keyBy('account_id');

        $accounts = FinAccount::query()->where('tenant_id', $tenantId)->orderBy('code')->get();
        $net = fn (FinAccount $account): int => (int) ($totals->get($account->id)->debit ?? 0) - (int) ($totals->get($account->id)->credit ?? 0);

        $section = fn (string $type, int $sign): Collection => $accounts->where('type', $type)
            ->map(fn (FinAccount $account): array => ['account' => $account, 'amount' => $sign * $net($account)])
            ->filter(fn (array $row): bool => $row['amount'] !== 0)->values();

        $assets = $section('asset', 1);
        $liabilities = $section('liability', -1);
        $equity = $section('equity', -1);

        $profitToDate = -(int) $accounts->whereIn('type', ['income', 'expense'])->sum($net);
        $yearStart = $this->fiscalYearStart($tenantId, $asOf);
        $currentYear = $this->profitAndLoss($tenantId, $yearStart, $asOf)['net_profit'];
        $priorYears = $profitToDate - $currentYear;

        $totalAssets = (int) $assets->sum('amount');
        $totalLiabilities = (int) $liabilities->sum('amount');
        $totalEquity = (int) $equity->sum('amount') + $profitToDate;

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'current_year_profit' => $currentYear,
            'prior_years_profit' => $priorYears,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'balanced' => $totalAssets === $totalLiabilities + $totalEquity,
        ];
    }

    /**
     * Cash movements by what caused them. A cash movement is attributed to the other accounts in the same journal,
     * so every unit of cash that moved is explained. Investing and financing are picked by account code.
     *
     * @return array{opening: int, closing: int, sections: array<string, array{label: string, rows: Collection<int, array{account: FinAccount, amount: int}>, total: int}>, net_change: int}
     */
    public function cashFlow(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $cashIds = $this->cashAccountIds($tenantId);

        $cashBalance = fn (?string $before, ?string $upTo): int => (int) $this->lines($tenantId)
            ->whereIn('l.account_id', $cashIds)
            ->when($before, fn (Builder $query) => $query->whereDate('j.date', '<', $before))
            ->when($upTo, fn (Builder $query) => $query->whereDate('j.date', '<=', $upTo))
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as total')->value('total');

        $opening = $cashBalance($from->toDateString(), null);
        $closing = $cashBalance(null, $to->toDateString());

        $cashJournalIds = $this->lines($tenantId)->whereIn('l.account_id', $cashIds)
            ->whereDate('j.date', '>=', $from->toDateString())->whereDate('j.date', '<=', $to->toDateString())
            ->select('l.journal_id');

        $counter = $this->lines($tenantId)
            ->whereIn('l.journal_id', $cashJournalIds)
            ->whereNotIn('l.account_id', $cashIds)
            ->groupBy('l.account_id')
            ->selectRaw('l.account_id, SUM(l.debit - l.credit) as net')
            ->get();

        $accounts = FinAccount::query()->where('tenant_id', $tenantId)->whereIn('id', $counter->pluck('account_id'))->orderBy('code')->get()->keyBy('id');

        $sections = [
            'operating' => ['label' => 'Operating activities', 'rows' => collect(), 'total' => 0],
            'investing' => ['label' => 'Investing activities', 'rows' => collect(), 'total' => 0],
            'financing' => ['label' => 'Financing activities', 'rows' => collect(), 'total' => 0],
        ];

        foreach ($counter as $row) {
            $account = $accounts->get($row->account_id);
            $effect = -(int) $row->net;
            if (! $account instanceof FinAccount || $effect === 0) {
                continue;
            }

            $key = match (true) {
                $this->startsWithAny($account->code, self::INVESTING_PREFIXES) => 'investing',
                $account->type === 'equity' || $this->startsWithAny($account->code, self::FINANCING_PREFIXES) => 'financing',
                default => 'operating',
            };
            $sections[$key]['rows']->push(['account' => $account, 'amount' => $effect]);
            $sections[$key]['total'] += $effect;
        }

        foreach ($sections as $key => $section) {
            $sections[$key]['rows'] = $section['rows']->sortBy(fn (array $row): string => $row['account']->code)->values();
        }

        return ['opening' => $opening, 'closing' => $closing, 'sections' => $sections, 'net_change' => $closing - $opening];
    }

    /**
     * Income, spending and the running balance of each fund. Lines with no fund are grouped as "No fund".
     *
     * @return array{rows: list<array{fund: FinFund|null, name: string, income: int, expenses: int, net: int, balance: int}>, totals: array{income: int, expenses: int, net: int, balance: int}}
     */
    public function fundReport(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $query = fn (?string $start) => $this->lines($tenantId)
            ->whereIn('a.type', ['income', 'expense'])
            ->when($start, fn (Builder $q) => $q->whereDate('j.date', '>=', $start))
            ->whereDate('j.date', '<=', $to->toDateString())
            ->groupBy('l.fund_id', 'a.type')
            ->selectRaw('l.fund_id, a.type, SUM(l.debit) as debit, SUM(l.credit) as credit')
            ->get();

        $period = $query($from->toDateString());
        $cumulative = $query(null);

        $sum = function (Collection $rows, ?int $fundId, string $type): int {
            $row = $rows->first(fn ($candidate): bool => ($candidate->fund_id === null ? null : (int) $candidate->fund_id) === $fundId && $candidate->type === $type);

            return $row === null ? 0 : ($type === 'income' ? (int) $row->credit - (int) $row->debit : (int) $row->debit - (int) $row->credit);
        };

        $funds = FinFund::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $entries = $funds->map(fn (FinFund $fund): array => ['fund' => $fund, 'id' => $fund->id, 'name' => $fund->name])
            ->push(['fund' => null, 'id' => null, 'name' => 'No fund']);

        $rows = $entries->map(function (array $entry) use ($period, $cumulative, $sum): array {
            $income = $sum($period, $entry['id'], 'income');
            $expenses = $sum($period, $entry['id'], 'expense');

            return [
                'fund' => $entry['fund'],
                'name' => $entry['name'],
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income - $expenses,
                'balance' => $sum($cumulative, $entry['id'], 'income') - $sum($cumulative, $entry['id'], 'expense'),
            ];
        })->filter(fn (array $row): bool => $row['fund'] !== null || $row['income'] !== 0 || $row['expenses'] !== 0 || $row['balance'] !== 0)->values()->all();

        $collected = collect($rows);

        return [
            'rows' => $rows,
            'totals' => [
                'income' => (int) $collected->sum('income'),
                'expenses' => (int) $collected->sum('expenses'),
                'net' => (int) $collected->sum('net'),
                'balance' => (int) $collected->sum('balance'),
            ],
        ];
    }

    /** @return list<int> */
    public function cashAccountIds(string $tenantId): array
    {
        $systemCash = FinAccount::query()->where('tenant_id', $tenantId)
            ->whereIn('system_key', [ChartOfAccounts::BANK, ChartOfAccounts::GATEWAY_CLEARING])->pluck('id');
        $banks = FinBankAccount::query()->where('tenant_id', $tenantId)->pluck('account_id');

        return $systemCash->merge($banks)->unique()->values()->all();
    }

    private function lines(string $tenantId): Builder
    {
        return DB::connection('tenant')->table('fin_journal_lines as l')
            ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
            ->join('fin_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('j.tenant_id', $tenantId);
    }

    /** @param  list<string>  $prefixes */
    private function startsWithAny(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
