<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StoreOrder extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'store_orders';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'total_amount_cents',
        'currency',
        'status',
        'payment_method',
    ];

    /**
     * @return HasMany<StoreOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class, 'store_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'total_amount_cents' => 'integer',
        ];
    }
}
