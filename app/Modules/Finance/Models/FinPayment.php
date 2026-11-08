<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinPayment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_payments';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(FinContact::class, 'contact_id');
    }

    /** @return HasMany<FinPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(FinPaymentAllocation::class, 'payment_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'integer'];
    }
}
