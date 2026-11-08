<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class FinFund extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_funds';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_restricted' => 'boolean', 'is_active' => 'boolean'];
    }
}
