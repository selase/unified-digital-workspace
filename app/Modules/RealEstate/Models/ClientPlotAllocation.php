<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $land_parcel_id
 * @property string $client_user_id
 * @property string $allocation_number
 * @property int $total_price_pesewas
 * @property int $amount_paid_pesewas
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $allocated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class ClientPlotAllocation extends Model
{
    use BelongsToTenant;

    protected $table = 'client_plot_allocations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'land_parcel_id',
        'client_user_id',
        'allocation_number',
        'total_price_pesewas',
        'amount_paid_pesewas',
        'status',
        'allocated_at',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<LandParcel, $this>
     */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class, 'land_parcel_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id', 'uuid');
    }

    /**
     * @return HasMany<InstallmentSchedule, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class, 'client_plot_allocation_id');
    }

    public function outstandingBalancePesewas(): int
    {
        return max(0, $this->total_price_pesewas - $this->amount_paid_pesewas);
    }

    public function formattedTotalPrice(): string
    {
        return 'GHS '.number_format($this->total_price_pesewas / 100, 2);
    }

    public function formattedAmountPaid(): string
    {
        return 'GHS '.number_format($this->amount_paid_pesewas / 100, 2);
    }

    public function formattedOutstandingBalance(): string
    {
        return 'GHS '.number_format($this->outstandingBalancePesewas() / 100, 2);
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_price_pesewas' => 'integer',
            'amount_paid_pesewas' => 'integer',
            'allocated_at' => 'datetime',
        ];
    }
}
