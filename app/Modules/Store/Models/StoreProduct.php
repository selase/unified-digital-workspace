<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property string|null $description
 * @property int $price_cents
 * @property string $currency
 * @property int $stock_quantity
 * @property bool $is_active
 * @property string $audience
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StoreOrderItem> $orderItems
 */
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
