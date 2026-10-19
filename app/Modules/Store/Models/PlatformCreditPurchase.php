<?php

declare(strict_types=1);

namespace App\Modules\Store\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $package_id
 * @property string $payment_reference
 * @property int $amount_paid_cents
 * @property int $credits_awarded
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PlatformCreditPackage|null $package
 */
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
