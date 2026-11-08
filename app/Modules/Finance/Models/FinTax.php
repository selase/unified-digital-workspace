<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class FinTax extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_taxes';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['components' => 'array', 'is_active' => 'boolean', 'rate_basis_points' => 'integer'];
    }
}
