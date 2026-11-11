<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinItem extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_items';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return HasMany<FinStockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(FinStockMovement::class, 'item_id');
    }

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'is_active' => 'boolean', 'track_stock' => 'boolean', 'reorder_level_thousandths' => 'integer'];
    }
}
