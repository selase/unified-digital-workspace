<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EscrowAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_PENDING_FUNDING = 'pending_funding';

    public const STATUS_FUNDED = 'funded';

    public const STATUS_FROZEN_DISPUTED = 'frozen_disputed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'vendor_escrow_accounts';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'escrow_number',
        'proforma_invoice_id',
        'service_job_id',
        'vendor_profile_id',
        'buyer_id',
        'currency',
        'funded_amount_pesewas',
        'held_balance_pesewas',
        'released_vendor_pesewas',
        'released_tenant_commission_pesewas',
        'released_platform_commission_pesewas',
        'refunded_buyer_pesewas',
        'status',
        'funded_at',
        'settled_at',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function formattedHeldBalance(): string
    {
        return $this->currency.' '.number_format($this->held_balance_pesewas / 100, 2);
    }

    /**
     * @return BelongsTo<ProformaInvoice, $this>
     */
    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class, 'proforma_invoice_id');
    }

    /**
     * @return BelongsTo<ServiceJob, $this>
     */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'service_job_id');
    }

    /**
     * @return BelongsTo<VendorProfile, $this>
     */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id', 'uuid');
    }

    /**
     * @return HasMany<EscrowLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EscrowLedgerEntry::class, 'escrow_account_id');
    }

    /**
     * @return HasMany<EscrowDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(EscrowDispute::class, 'escrow_account_id');
    }

    protected function casts(): array
    {
        return [
            'funded_amount_pesewas' => 'integer',
            'held_balance_pesewas' => 'integer',
            'released_vendor_pesewas' => 'integer',
            'released_tenant_commission_pesewas' => 'integer',
            'released_platform_commission_pesewas' => 'integer',
            'refunded_buyer_pesewas' => 'integer',
            'funded_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }
}
