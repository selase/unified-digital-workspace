<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money paid to a supplier against one bill. Voiding keeps the row and reverses its journal. */
final class FinBillPayment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_bill_payments';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(FinBill::class, 'bill_id');
    }

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'integer', 'voided_at' => 'datetime'];
    }
}
