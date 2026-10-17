<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

final class TenantCreditWallet extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'tenant_credit_wallets';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'credit_type',
        'balance',
        'total_purchased',
        'total_consumed',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'total_purchased' => 'integer',
            'total_consumed' => 'integer',
        ];
    }
}
