<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $tenant_id
 * @property int $store_order_id
 * @property int $store_product_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $total_price_cents
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read StoreOrder $order
 * @property-read StoreProduct $product
 */
final class StoreOrderItem extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'store_order_items';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'store_order_id',
        'store_product_id',
        'quantity',
        'unit_price_cents',
        'total_price_cents',
    ];

    /**
     * @return BelongsTo<StoreOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /**
     * @return BelongsTo<StoreProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'total_price_cents' => 'integer',
        ];
    }
}
