<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinBankAccount;
use App\Modules\Finance\Models\FinBankTransaction;
use App\Modules\Finance\Models\FinContact;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Models\FinJournalLine;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Matches statement lines to the ledger movements on the bank's account. A statement
 * line matches exactly one ledger line of the same amount, and a ledger line is matched once.
 * Reversed entries and their reversals are ignored because they cancel out.
 */
final class BankReconciliation
{
    private const SUGGEST_WINDOW_DAYS = 7;

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ExpenseService $expenses,
    ) {}

    /** Ledger movements on the bank's account that no statement line has been matched to yet. @return Builder<FinJournalLine> */
    public function unmatchedLedgerLines(FinBankAccount $bank): Builder
    {
        return FinJournalLine::query()
            ->where('account_id', $bank->account_id)
            ->whereHas('journal', fn ($query) => $query
                ->where('tenant_id', $bank->tenant_id)
                ->where('status', FinJournal::STATUS_POSTED)
                ->whereNull('reverses_journal_id'))
            ->whereNotIn('id', FinBankTransaction::query()->whereNotNull('journal_line_id')->select('journal_line_id'));
    }

    /**
     * Proposes a ledger line for each unmatched statement line: same amount, nearest date within a week.
     *
     * @return array<int, FinJournalLine> statement line id => ledger line
     */
    public function suggestions(FinBankAccount $bank): array
    {
        $candidates = $this->unmatchedLedgerLines($bank)->with('journal')->get();
        $used = [];
        $suggested = [];

        $statementLines = FinBankTransaction::query()
            ->where('bank_account_id', $bank->id)
            ->where('status', FinBankTransaction::STATUS_UNMATCHED)
            ->orderBy('date')->orderBy('id')->get();

        foreach ($statementLines as $transaction) {
            $best = $candidates
                ->filter(fn (FinJournalLine $line): bool => ! isset($used[$line->id])
                    && $line->debit - $line->credit === $transaction->amount
                    && abs($line->journal->date->diffInDays($transaction->date)) <= self::SUGGEST_WINDOW_DAYS)
                ->sortBy(fn (FinJournalLine $line): float|int => abs($line->journal->date->diffInDays($transaction->date)))
                ->first();

            if ($best instanceof FinJournalLine) {
                $used[$best->id] = true;
                $suggested[$transaction->id] = $best;
            }
        }

        return $suggested;
    }

    /** Applies every suggestion. Returns how many lines were matched. */
    public function autoMatch(FinBankAccount $bank): int
    {
        $suggestions = $this->suggestions($bank);

        DB::connection('tenant')->transaction(function () use ($bank, $suggestions): void {
            foreach ($suggestions as $transactionId => $line) {
                $this->match(FinBankTransaction::query()->where('bank_account_id', $bank->id)->findOrFail($transactionId), $line->id);
            }
        });

        return count($suggestions);
    }

    public function match(FinBankTransaction $transaction, int $journalLineId): FinBankTransaction
    {
        if ($transaction->status !== FinBankTransaction::STATUS_UNMATCHED) {
            throw new DomainException('This statement line is already matched or excluded.');
        }

        $bank = $transaction->bankAccount()->firstOrFail();
        $line = $this->unmatchedLedgerLines($bank)->whereKey($journalLineId)->first();

        if (! $line instanceof FinJournalLine) {
            throw new DomainException('That ledger entry is not available to match.');
        }
        if ($line->debit - $line->credit !== $transaction->amount) {
            throw new DomainException('The amounts differ. A statement line can only be matched to a ledger entry of the same amount.');
        }

        $transaction->forceFill([
            'journal_line_id' => $line->id,
            'status' => FinBankTransaction::STATUS_MATCHED,
            'matched_at' => now(),
        ])->save();

        return $transaction;
    }

    public function unmatch(FinBankTransaction $transaction): FinBankTransaction
    {
        $transaction->forceFill(['journal_line_id' => null, 'status' => FinBankTransaction::STATUS_UNMATCHED, 'matched_at' => null])->save();

        return $transaction;
    }

    /** Marks a line as one that does not belong in the books, such as a duplicate or a personal item. */
    public function exclude(FinBankTransaction $transaction): FinBankTransaction
    {
        if ($transaction->status === FinBankTransaction::STATUS_MATCHED) {
            throw new DomainException('Unmatch this line before excluding it.');
        }

        $transaction->forceFill(['status' => FinBankTransaction::STATUS_EXCLUDED])->save();

        return $transaction;
    }

    public function restore(FinBankTransaction $transaction): FinBankTransaction
    {
        if ($transaction->status === FinBankTransaction::STATUS_EXCLUDED) {
            $transaction->forceFill(['status' => FinBankTransaction::STATUS_UNMATCHED])->save();
        }

        return $transaction;
    }

    /**
     * Books a statement line that has no entry yet and matches it: money out becomes an
     * expense paid from this bank account, money in becomes a deposit to the chosen income account.
     */
    public function recordFromStatement(FinBankTransaction $transaction, int $accountId, ?FinContact $contact = null, ?string $userId = null): FinBankTransaction
    {
        if ($transaction->status !== FinBankTransaction::STATUS_UNMATCHED) {
            throw new DomainException('This statement line is already matched or excluded.');
        }

        $bank = $transaction->bankAccount()->firstOrFail();
        $account = FinAccount::query()->where('tenant_id', $bank->tenant_id)->findOrFail($accountId);
        $memo = $transaction->description ?: 'Bank statement line';

        return DB::connection('tenant')->transaction(function () use ($transaction, $bank, $account, $contact, $userId, $memo): FinBankTransaction {
            if ($transaction->amount < 0) {
                if ($account->type !== 'expense') {
                    throw new DomainException('Money going out must be booked to an expense account.');
                }
                $journalId = $this->expenses->record(
                    $bank->tenant_id,
                    abs($transaction->amount),
                    $transaction->date,
                    $account->id,
                    $bank->account_id,
                    $contact,
                    ['description' => $memo, 'reference' => $transaction->reference],
                    $userId,
                )->journal_id;
            } else {
                if (! in_array($account->type, ['income', 'liability', 'equity'], true)) {
                    throw new DomainException('Money coming in must be booked to an income, liability or equity account. Customer payments should be recorded against their invoices.');
                }
                $journalId = $this->ledger->post(
                    $bank->tenant_id,
                    $transaction->date,
                    [
                        ['account_id' => $bank->account_id, 'debit' => $transaction->amount, 'memo' => $memo, 'contact_id' => $contact?->id],
                        ['account_id' => $account->id, 'credit' => $transaction->amount, 'memo' => $memo, 'contact_id' => $contact?->id],
                    ],
                    'Bank deposit: '.$memo,
                    'bank_statement',
                    $transaction->id,
                    $userId,
                )->id;
            }

            $line = FinJournalLine::query()->where('journal_id', $journalId)->where('account_id', $bank->account_id)->firstOrFail();

            return $this->match($transaction, $line->id);
        });
    }

    /**
     * @return array{ledger_balance: int, statement_balance: int, difference: int, unmatched_statement: int, unmatched_statement_total: int, outstanding_ledger: int, outstanding_ledger_total: int, is_reconciled: bool}
     */
    public function summary(FinBankAccount $bank): array
    {
        $ledger = FinJournalLine::query()->where('account_id', $bank->account_id)
            ->whereHas('journal', fn ($query) => $query->where('tenant_id', $bank->tenant_id))
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();
        $ledgerBalance = (int) $ledger->d - (int) $ledger->c;

        $statementBalance = $bank->opening_balance + (int) FinBankTransaction::query()->where('bank_account_id', $bank->id)->sum('amount');

        $open = FinBankTransaction::query()->where('bank_account_id', $bank->id)->where('status', '!=', FinBankTransaction::STATUS_MATCHED);
        $unmatchedCount = (clone $open)->count();
        $unmatchedTotal = (int) (clone $open)->sum('amount');

        $outstanding = $this->unmatchedLedgerLines($bank)->get();
        $outstandingTotal = (int) $outstanding->sum(fn (FinJournalLine $line): int => $line->debit - $line->credit);

        $difference = $statementBalance - $ledgerBalance;

        return [
            'ledger_balance' => $ledgerBalance,
            'statement_balance' => $statementBalance,
            'difference' => $difference,
            'unmatched_statement' => $unmatchedCount,
            'unmatched_statement_total' => $unmatchedTotal,
            'outstanding_ledger' => $outstanding->count(),
            'outstanding_ledger_total' => $outstandingTotal,
            'is_reconciled' => $difference === 0 && $unmatchedCount === 0 && $outstanding->isEmpty(),
        ];
    }

    /** @return Collection<int, FinJournalLine> */
    public function candidatesFor(FinBankTransaction $transaction, FinBankAccount $bank): Collection
    {
        return $this->unmatchedLedgerLines($bank)->with('journal')->get()
            ->filter(fn (FinJournalLine $line): bool => $line->debit - $line->credit === $transaction->amount)
            ->values();
    }
}
