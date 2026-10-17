<?php

declare(strict_types=1);

namespace App\Modules\VendorMarketplace\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EscrowLedgerEntry extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'vendor_escrow_ledger_entries';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'escrow_account_id',
        'idempotency_key',
        'entry_type',
        'direction',
        'beneficiary_type',
        'amount_pesewas',
        'balance_after_pesewas',
        'actor_id',
        'notes',
        'occurred_at',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'amount_pesewas' => 'integer',
            'balance_after_pesewas' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
