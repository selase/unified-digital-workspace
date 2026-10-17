<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $credit_type
 * @property int $amount_credits
 * @property int $price_cents
 * @property string $currency
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
