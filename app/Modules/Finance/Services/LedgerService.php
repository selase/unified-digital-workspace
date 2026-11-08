<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinJournalLine;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The only way money is recorded. A journal either balances and is posted whole,
 * or nothing is written. Posted journals are never edited: corrections reverse them.
 */
final class LedgerService
{
    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
    ) {}

    /**
     * @param  list<array{account_id: int, debit?: int, credit?: int, memo?: string|null, contact_id?: int|null, fund_id?: int|null}>  $lines
     */
    public function post(
        string $tenantId,
        CarbonInterface $date,
        array $lines,
        ?string $memo = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $createdBy = null,
        ?int $reversesJournalId = null,
    ): FinJournal {
        $this->assertPostable($tenantId, $date, $lines);

        return DB::connection('tenant')->transaction(function () use ($tenantId, $date, $lines, $memo, $sourceType, $sourceId, $createdBy, $reversesJournalId): FinJournal {
            $settings = $this->settings->for($tenantId);

            $journal = FinJournal::query()->create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'journal', $this->settings->prefix($settings, 'journal')),
                'date' => $date->toDateString(),
                'memo' => $memo,
                'status' => FinJournal::STATUS_POSTED,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reverses_journal_id' => $reversesJournalId,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                FinJournalLine::query()->create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'memo' => $line['memo'] ?? null,
                    'contact_id' => $line['contact_id'] ?? null,
                    'fund_id' => $line['fund_id'] ?? null,
                ]);
            }

            return $journal;
        });
    }

    public function reverse(FinJournal $journal, ?CarbonInterface $date = null, ?string $createdBy = null): FinJournal
    {
        if ($journal->status === FinJournal::STATUS_REVERSED) {
            throw new DomainException('This journal has already been reversed.');
        }

        if ($journal->reverses_journal_id !== null) {
            throw new DomainException('A reversing journal cannot itself be reversed.');
        }

        $date ??= today();
        $lines = $journal->lines()->get()->map(fn (FinJournalLine $line): array => [
            'account_id' => $line->account_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'memo' => $line->memo,
            'contact_id' => $line->contact_id,
            'fund_id' => $line->fund_id,
        ])->all();

        return DB::connection('tenant')->transaction(function () use ($journal, $date, $lines, $createdBy): FinJournal {
            $reversal = $this->post(
                $journal->tenant_id,
                $date,
                $lines,
                'Reversal of '.$journal->number,
                $journal->source_type,
                $journal->source_id,
                $createdBy,
                $journal->id,
            );

            $journal->forceFill(['status' => FinJournal::STATUS_REVERSED])->save();

            return $reversal;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertPostable(string $tenantId, CarbonInterface $date, array $lines): void
    {
        if (count($lines) < 2) {
            throw new DomainException('A journal needs at least two lines.');
        }

        $debits = 0;
        $credits = 0;
        $accountIds = [];

        foreach ($lines as $line) {
            $debit = (int) ($line['debit'] ?? 0);
            $credit = (int) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0 || ($debit > 0) === ($credit > 0)) {
                throw new DomainException('Each journal line must have either a debit or a credit amount, not both or neither.');
            }

            $debits += $debit;
            $credits += $credit;
            $accountIds[] = (int) $line['account_id'];
        }

        if ($debits !== $credits) {
            throw new DomainException('The journal does not balance: debits and credits must be equal.');
        }

        $accountIds = array_values(array_unique($accountIds));
        $valid = FinAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $accountIds)
            ->count();

        if ($valid !== count($accountIds)) {
            throw new DomainException('A journal line uses an account that does not exist or is inactive.');
        }

        $lockDate = $this->settings->for($tenantId)->lock_date;
        if ($lockDate !== null && $date->lte($lockDate)) {
            throw new DomainException('The period up to '.$lockDate->toDateString().' is locked.');
        }
    }
}
