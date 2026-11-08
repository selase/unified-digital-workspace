<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinPaymentAllocation extends Model
{
    public $timestamps = false;

    protected $table = 'fin_payment_allocations';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected $connection = 'tenant';

    /** @return BelongsTo<FinInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinInvoice::class, 'invoice_id');
    }
}
