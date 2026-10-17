<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StoreProduct extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'store_products';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'sku',
        'description',
        'price_cents',
        'currency',
        'stock_quantity',
        'is_active',
        'audience',
    ];

    /**
     * @return HasMany<StoreOrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class, 'store_product_id');
    }

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
