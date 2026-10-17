<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $tenant_id
 * @property string $order_number
 * @property string|null $user_id
 * @property string $customer_name
 * @property string $customer_email
 * @property int $total_amount_cents
 * @property string $currency
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StoreOrderItem> $items
 * @property-read User|null $user
 */
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
        'payment_reference',
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
