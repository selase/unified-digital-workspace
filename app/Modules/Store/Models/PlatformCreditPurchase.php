<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformCreditPurchase extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'platform_credit_purchases';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'package_id',
        'payment_reference',
        'amount_paid_cents',
        'credits_awarded',
        'status',
    ];

    /**
     * @return BelongsTo<PlatformCreditPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(PlatformCreditPackage::class, 'package_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_paid_cents' => 'integer',
            'credits_awarded' => 'integer',
        ];
    }
}
