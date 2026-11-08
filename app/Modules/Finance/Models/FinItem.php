<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class FinItem extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_items';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'is_active' => 'boolean'];
    }
}
