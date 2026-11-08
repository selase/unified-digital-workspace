<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class FinAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const TYPES = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'income' => 'Income',
        'expense' => 'Expense',
    ];

    /** Types whose natural balance is a debit. */
    public const DEBIT_TYPES = ['asset', 'expense'];

    protected $table = 'fin_accounts';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function isDebitNormal(): bool
    {
        return in_array($this->type, self::DEBIT_TYPES, true);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
