<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A cost paid straight away (card, cash, mobile money) with no bill. */
final class FinExpense extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_expenses';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    /** @return BelongsTo<FinAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id');
    }

    /** @return BelongsTo<FinAccount, $this> */
    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'paid_from_account_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'integer', 'voided_at' => 'datetime'];
    }
}
