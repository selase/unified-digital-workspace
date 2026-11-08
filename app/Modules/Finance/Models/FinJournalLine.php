<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinJournalLine extends Model
{
    public $timestamps = false;

    protected $table = 'fin_journal_lines';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected $connection = 'tenant';

    /** @return BelongsTo<FinAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id');
    }

    /** @return BelongsTo<FinJournal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(FinJournal::class, 'journal_id');
    }

    protected function casts(): array
    {
        return ['debit' => 'integer', 'credit' => 'integer'];
    }
}
