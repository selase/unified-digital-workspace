<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinBillLine extends Model
{
    public $timestamps = false;

    protected $table = 'fin_bill_lines';

    protected $connection = 'tenant';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
