<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class FinSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'fin_settings';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tax_enabled' => 'boolean', 'lock_date' => 'date', 'prefixes' => 'array', 'fiscal_year_start_month' => 'integer', 'default_payment_terms_days' => 'integer'];
    }
}
