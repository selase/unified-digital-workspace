<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinInvoiceLine extends Model
{
    public $timestamps = false;

    protected $table = 'fin_invoice_lines';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected $connection = 'tenant';

    /** @return BelongsTo<FinTax, $this> */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(FinTax::class, 'tax_id');
    }

    protected function casts(): array
    {
        return ['quantity_thousandths' => 'integer', 'unit_price' => 'integer', 'line_total' => 'integer', 'tax_amount' => 'integer'];
    }
}
