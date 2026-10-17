<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class InstallmentSchedule extends Model
{
    use BelongsToTenant;

    protected $table = 'installment_schedules';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'client_plot_allocation_id',
        'installment_number',
        'due_date',
        'amount_due_pesewas',
        'amount_paid_pesewas',
        'status',
        'paid_at',
        'payment_reference',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<ClientPlotAllocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ClientPlotAllocation::class, 'client_plot_allocation_id');
    }

    public function formattedAmountDue(): string
    {
        return 'GHS '.number_format($this->amount_due_pesewas / 100, 2);
    }

    public function formattedAmountPaid(): string
    {
        return 'GHS '.number_format($this->amount_paid_pesewas / 100, 2);
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
            'installment_number' => 'integer',
            'due_date' => 'date',
            'amount_due_pesewas' => 'integer',
            'amount_paid_pesewas' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
