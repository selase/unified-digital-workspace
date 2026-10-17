<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $invoice_number
 * @property int|null $service_job_id
 * @property int|null $vendor_profile_id
 * @property string|null $client_id
 * @property string $currency
 * @property int $gross_amount_pesewas
 * @property int $tenant_commission_bps
 * @property int $platform_commission_bps
 * @property int $tenant_commission_pesewas
 * @property int $platform_commission_pesewas
 * @property int $net_vendor_payout_pesewas
 * @property array<mixed>|null $line_items
 * @property string|null $notes
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
final class ProformaInvoice extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_FUNDED = 'funded';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'vendor_proforma_invoices';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'invoice_number',
        'service_job_id',
        'vendor_profile_id',
        'client_id',
        'currency',
        'gross_amount_pesewas',
        'tenant_commission_bps',
        'platform_commission_bps',
        'tenant_commission_pesewas',
        'platform_commission_pesewas',
        'net_vendor_payout_pesewas',
        'line_items',
        'notes',
        'status',
        'valid_until',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function formattedGross(): string
    {
        return $this->currency.' '.number_format($this->gross_amount_pesewas / 100, 2);
    }

    public function formattedNetVendor(): string
    {
        return $this->currency.' '.number_format($this->net_vendor_payout_pesewas / 100, 2);
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
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id', 'uuid');
    }

    /**
     * @return HasOne<EscrowAccount, $this>
     */
    public function escrowAccount(): HasOne
    {
        return $this->hasOne(EscrowAccount::class, 'proforma_invoice_id');
    }

    protected function casts(): array
    {
        return [
            'gross_amount_pesewas' => 'integer',
            'tenant_commission_bps' => 'integer',
            'platform_commission_bps' => 'integer',
            'tenant_commission_pesewas' => 'integer',
            'platform_commission_pesewas' => 'integer',
            'net_vendor_payout_pesewas' => 'integer',
            'line_items' => 'array',
            'valid_until' => 'date',
        ];
    }
}
