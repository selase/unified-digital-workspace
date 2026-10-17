<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommissionRateCard extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'vendor_commission_rate_cards';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'category',
        'label',
        'tenant_commission_bps',
        'platform_commission_bps',
        'is_active',
        'approved_by_id',
        'approved_at',
    ];

    /**
     * Calculate exact 3-way split in minor units (pesewas) from configured basis points.
     * Zero invented fees: if no active rate card exists for a category, basis points default to 0.
     *
     * @return array{
     *     tenant_commission_bps: int,
     *     platform_commission_bps: int,
     *     tenant_commission_pesewas: int,
     *     platform_commission_pesewas: int,
     *     net_vendor_payout_pesewas: int
     * }
     */
    public static function computeSplitForCategory(string $category, int $grossAmountPesewas): array
    {
        $card = self::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->first();

        $tenantBps = $card ? (int) $card->tenant_commission_bps : 0;
        $platformBps = $card ? (int) $card->platform_commission_bps : 0;

        $tenantFee = (int) floor(($grossAmountPesewas * $tenantBps) / 10000);
        $platformFee = (int) floor(($grossAmountPesewas * $platformBps) / 10000);
        $netVendor = max(0, $grossAmountPesewas - $tenantFee - $platformFee);

        return [
            'tenant_commission_bps' => $tenantBps,
            'platform_commission_bps' => $platformBps,
            'tenant_commission_pesewas' => $tenantFee,
            'platform_commission_pesewas' => $platformFee,
            'net_vendor_payout_pesewas' => $netVendor,
        ];
    }

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'tenant_commission_bps' => 'integer',
            'platform_commission_bps' => 'integer',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }
}
