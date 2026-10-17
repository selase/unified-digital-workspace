<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class PlatformCreditPackage extends Model
{
    use HasUuid;

    protected $table = 'platform_credit_packages';

    protected $fillable = [
        'uuid',
        'name',
        'credit_type',
        'amount_credits',
        'price_cents',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_credits' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
