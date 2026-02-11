<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OfferNegotiation model - Negotiation tracking.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $offer_id
 * @property int $round
 * @property string $initiated_by
 * @property string|null $request
 * @property string|null $response
 * @property string $status
 * @property string|null $requested_salary
 * @property string|null $offered_salary
 * @property array<string, mixed>|null $requested_benefits
 * @property array<string, mixed>|null $offered_benefits
 * @property int|null $handled_by
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class OfferNegotiation extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_offer_negotiations';

    protected $fillable = [
        'tenant_id',
        'offer_id',
        'round',
        'initiated_by',
        'request',
        'response',
        'status',
        'requested_salary',
        'offered_salary',
        'requested_benefits',
        'offered_benefits',
        'handled_by',
        'responded_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'round' => 1,
        'initiated_by' => 'candidate',
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<JobOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class, 'offer_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function accept(int $handledById, ?string $response = null): bool
    {
        $this->status = 'accepted';
        $this->handled_by = $handledById;
        $this->response = $response;
        $this->responded_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function reject(int $handledById, string $response): bool
    {
        $this->status = 'rejected';
        $this->handled_by = $handledById;
        $this->response = $response;
        $this->responded_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * @param  array<string, mixed>|null  $offeredBenefits
     */
    public function counterOffer(int $handledById, float $offeredSalary, ?array $offeredBenefits = null, ?string $response = null): bool
    {
        $this->status = 'counter_offered';
        $this->handled_by = $handledById;
        $this->offered_salary = (string) $offeredSalary;
        $this->offered_benefits = $offeredBenefits;
        $this->response = $response;
        $this->responded_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForOffer($query, int $offerId)
    {
        return $query->where('offer_id', $offerId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'requested_salary' => 'decimal:2',
            'offered_salary' => 'decimal:2',
            'requested_benefits' => 'array',
            'offered_benefits' => 'array',
            'responded_at' => 'datetime',
        ];
    }
}
