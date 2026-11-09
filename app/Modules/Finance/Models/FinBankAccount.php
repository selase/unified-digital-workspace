<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A real bank or mobile-money account, tied to the asset account in the ledger that tracks it. */
final class FinBankAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_bank_accounts';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id');
    }

    /** @return HasMany<FinBankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinBankTransaction::class, 'bank_account_id');
    }

    protected function casts(): array
    {
        return ['opening_balance' => 'integer', 'is_active' => 'boolean'];
    }
}
