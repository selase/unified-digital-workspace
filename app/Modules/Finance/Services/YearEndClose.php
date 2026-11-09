<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinJournal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Closes a financial year: zeroes every income and expense account into retained earnings with one closing
 * journal dated the last day of the year, then locks the books up to that date. Reports leave closing journals
 * out of profit figures so the closed year still shows its profit.
 */
final class YearEndClose
{
    public const SOURCE = 'year_end_close';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ChartOfAccounts $accounts,
        private readonly FinanceSettings $settings,
        private readonly StatementReports $statements,
    ) {}

    /** The last day of the most recent complete financial year, which is the usual date to close. */
    public function suggestedDate(string $tenantId): CarbonImmutable
    {
        return $this->statements->fiscalYearStart($tenantId, today())->subDay();
    }

    /**
     * What closing the year ending on this date would do.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, income: int, expenses: int, net_profit: int, already_closed: bool}
     */
    public function preview(string $tenantId, CarbonInterface $yearEnd): array
    {
        $to = CarbonImmutable::parse($yearEnd->toDateString());
        $from = $this->statements->fiscalYearStart($tenantId, $to);
        $pl = $this->statements->profitAndLoss($tenantId, $from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'income' => $pl['total_income'],
            'expenses' => $pl['total_expenses'],
            'net_profit' => $pl['net_profit'],
            'already_closed' => FinJournal::query()->where('tenant_id', $tenantId)->where('source_type', self::SOURCE)->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->exists(),
        ];
    }

    public function close(string $tenantId, CarbonInterface $yearEnd, ?string $userId = null): FinJournal
    {
        $to = CarbonImmutable::parse($yearEnd->toDateString());
        if ($to->isFuture()) {
            throw new DomainException('A financial year cannot be closed before it ends.');
        }
        if ($this->statements->fiscalYearStart($tenantId, $to->addDay())->toDateString() !== $to->addDay()->toDateString()) {
            throw new DomainException('Choose the last day of a financial year.');
        }

        $preview = $this->preview($tenantId, $to);
        if ($preview['already_closed']) {
            throw new DomainException('This financial year has already been closed.');
        }

        return DB::connection('tenant')->transaction(function () use ($tenantId, $to, $preview, $userId): FinJournal {
            $rows = DB::connection('tenant')->table('fin_journal_lines as l')
                ->join('fin_journals as j', 'j.id', '=', 'l.journal_id')
                ->join('fin_accounts as a', 'a.id', '=', 'l.account_id')
                ->where('j.tenant_id', $tenantId)->whereIn('a.type', ['income', 'expense'])
                ->whereDate('j.date', '>=', $preview['from']->toDateString())->whereDate('j.date', '<=', $to->toDateString())
                ->where(fn ($query) => $query->whereNull('j.source_type')->orWhere('j.source_type', '!=', self::SOURCE))
                ->groupBy('l.account_id', 'l.fund_id')
                ->selectRaw('l.account_id, l.fund_id, SUM(l.debit - l.credit) as net')
                ->get();

            $lines = [];
            foreach ($rows as $row) {
                $net = (int) $row->net;
                if ($net === 0) {
                    continue;
                }
                $lines[] = ['account_id' => (int) $row->account_id, 'fund_id' => $row->fund_id !== null ? (int) $row->fund_id : null, 'memo' => 'Year-end close', $net > 0 ? 'credit' : 'debit' => abs($net)];
            }

            if ($lines === []) {
                throw new DomainException('There is nothing to close: no income or expenses in this year.');
            }

            $retained = $this->accounts->bySystemKey($tenantId, ChartOfAccounts::RETAINED_EARNINGS);
            $profit = array_sum(array_map(fn (array $line): int => ($line['debit'] ?? 0) - ($line['credit'] ?? 0), $lines));
            $lines[] = ['account_id' => $retained->id, 'memo' => 'Profit for the year moved to retained earnings', $profit >= 0 ? 'credit' : 'debit' => abs($profit)];

            $journal = $this->ledger->post($tenantId, $to, $lines, 'Year-end close '.$to->year, self::SOURCE, null, $userId);
            $this->settings->for($tenantId)->forceFill(['lock_date' => $to->toDateString()])->save();

            return $journal;
        });
    }

    /** Income and expense accounts exist only to be closed, so this just checks the chart is ready. */
    public function ready(string $tenantId): bool
    {
        return FinAccount::query()->where('tenant_id', $tenantId)->where('system_key', ChartOfAccounts::RETAINED_EARNINGS)->exists();
    }
}
