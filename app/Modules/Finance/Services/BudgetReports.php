<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinBudget;
use App\Modules\Finance\Models\FinBudgetLine;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Budget against what really happened. The budget is spread evenly over the months of the period, so the
 * "to date" figure is the part of the plan that should have been used by the chosen date.
 */
final class BudgetReports
{
    /**
     * @return array{rows: list<array{line: FinBudgetLine, budget: int, to_date: int, actual: int, variance: int, percent: int}>, totals: array{income_budget: int, income_actual: int, expense_budget: int, expense_actual: int}, elapsed_months: int, total_months: int}
     */
    public function compare(FinBudget $budget, ?CarbonInterface $asOf = null): array
    {
        $asOf = CarbonImmutable::parse(($asOf ?? today())->toDateString());
        $start = CarbonImmutable::parse($budget->starts_on->toDateString());
        $end = CarbonImmutable::parse($budget->ends_on->toDateString());
        $cutoff = $asOf->gt($end) ? $end : $asOf;

        $totalMonths = max(1, (int) ceil($start->diffInMonths($end->addDay(), true)));
        $elapsed = $cutoff->lt($start) ? 0 : min($totalMonths, (int) ceil($start->diffInMonths($cutoff->addDay(), true)));

        $budget->loadMissing('lines.account', 'lines.fund');
        $rows = [];
        $totals = ['income_budget' => 0, 'income_actual' => 0, 'expense_budget' => 0, 'expense_actual' => 0];

        foreach ($budget->lines->sortBy(fn (FinBudgetLine $line): string => $line->account->code) as $line) {
            $isIncome = $line->account->type === 'income';
            $sum = DB::connection('tenant')->table('fin_journal_lines as l')
                ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
                ->where('j.tenant_id', $budget->tenant_id)->where('l.account_id', $line->account_id)
                ->whereDate('j.date', '>=', $start->toDateString())->whereDate('j.date', '<=', $cutoff->toDateString())
                ->when($line->fund_id !== null, fn ($query) => $query->where('l.fund_id', $line->fund_id))
                ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as net')->value('net');

            $actual = $isIncome ? -(int) $sum : (int) $sum;
            $toDate = intdiv($line->amount * $elapsed, $totalMonths);
            $variance = $isIncome ? $actual - $toDate : $toDate - $actual;

            $rows[] = [
                'line' => $line,
                'budget' => $line->amount,
                'to_date' => $toDate,
                'actual' => $actual,
                'variance' => $variance,
                'percent' => $line->amount > 0 ? intdiv($actual * 100, $line->amount) : 0,
            ];
            $totals[$isIncome ? 'income_budget' : 'expense_budget'] += $line->amount;
            $totals[$isIncome ? 'income_actual' : 'expense_actual'] += $actual;
        }

        return ['rows' => $rows, 'totals' => $totals, 'elapsed_months' => $elapsed, 'total_months' => $totalMonths];
    }
}
