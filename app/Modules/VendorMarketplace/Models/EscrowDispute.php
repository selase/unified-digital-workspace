<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EscrowDispute extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const RESOLUTION_RELEASE_TO_VENDOR = 'release_to_vendor';

    public const RESOLUTION_REFUND_TO_BUYER = 'refund_to_buyer';

    protected $table = 'vendor_escrow_disputes';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'escrow_account_id',
        'raised_by_id',
        'reason',
        'status',
        'resolution_type',
        'resolution_notes',
        'resolved_by_id',
        'resolved_at',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<EscrowAccount, $this>
     */
    public function escrowAccount(): BelongsTo
    {
        return $this->belongsTo(EscrowAccount::class, 'escrow_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
