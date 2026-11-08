<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinRecurringInvoice extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const FREQUENCIES = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    protected $table = 'fin_recurring_invoices';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    protected function casts(): array
    {
        return ['lines' => 'array', 'next_run_date' => 'date', 'end_date' => 'date', 'auto_send' => 'boolean', 'is_active' => 'boolean', 'last_run_at' => 'datetime'];
    }
}
