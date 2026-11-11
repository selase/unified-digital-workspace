<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change in how much of an item is in stock. Quantities are in thousandths so part units work; the on-hand figure is their sum. */
final class FinStockMovement extends Model
{
    use BelongsToTenant;

    public const KINDS = ['opening' => 'Opening stock', 'purchase' => 'Received', 'sale' => 'Sold', 'return' => 'Returned by customer', 'adjustment' => 'Adjustment', 'void' => 'Reversed'];

    protected $table = 'fin_stock_movements';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(FinItem::class, 'item_id');
    }

    protected function casts(): array
    {
        return ['moved_on' => 'date', 'quantity_thousandths' => 'integer', 'unit_cost' => 'integer'];
    }
}
