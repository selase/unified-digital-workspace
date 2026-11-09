<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a bank statement. Positive amounts are money in, negative are money out. */
final class FinBankTransaction extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_EXCLUDED = 'excluded';

    protected $table = 'fin_bank_transactions';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** Ledger lines matched together when one statement line covers several entries. @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<FinJournalLine, $this> */
    public function matchedLines(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(FinJournalLine::class, 'fin_bank_matches', 'transaction_id', 'journal_line_id');
    }

    /** @return BelongsTo<FinBankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinBankAccount::class, 'bank_account_id');
    }

    /** @return BelongsTo<FinJournalLine, $this> */
    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(FinJournalLine::class, 'journal_line_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'integer', 'matched_at' => 'datetime'];
    }
}
