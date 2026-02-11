<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Enums\RecommendationType;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalRecommendation model - Final recommendations (promotion, training, etc.)
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property RecommendationType $type
 * @property string|null $description
 * @property string|null $action_plan
 * @property \Illuminate\Support\Carbon|null $target_date
 * @property int|null $recommended_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalRecommendation extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IMPLEMENTED = 'implemented';

    protected $table = 'hrms_appraisal_recommendations';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'type',
        'description',
        'action_plan',
        'target_date',
        'recommended_by',
        'approved_by',
        'approved_at',
        'status',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_IMPLEMENTED => 'Implemented',
        ];
    }

    /**
     * Get the appraisal this recommendation belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * Get the employee who made the recommendation.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function recommendedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recommended_by');
    }

    /**
     * Get the employee who approved the recommendation.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * Check if the recommendation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the recommendation is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the recommendation is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the recommendation has been implemented.
     */
    public function isImplemented(): bool
    {
        return $this->status === self::STATUS_IMPLEMENTED;
    }

    /**
     * Check if this is a positive recommendation.
     */
    public function isPositive(): bool
    {
        return $this->type->isPositive();
    }

    /**
     * Check if this is a corrective action.
     */
    public function isCorrective(): bool
    {
        return $this->type->isCorrective();
    }

    /**
     * Approve the recommendation.
     */
    public function approve(int $approverId): void
    {
        $this->approved_by = $approverId;
        $this->approved_at = \Illuminate\Support\Carbon::now();
        $this->status = self::STATUS_APPROVED;
        $this->save();
    }

    /**
     * Reject the recommendation.
     */
    public function reject(): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->save();
    }

    /**
     * Mark the recommendation as implemented.
     */
    public function markImplemented(): void
    {
        $this->status = self::STATUS_IMPLEMENTED;
        $this->save();
    }

    /**
     * Scope to filter by appraisal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForAppraisal($query, int $appraisalId)
    {
        return $query->where('appraisal_id', $appraisalId);
    }

    /**
     * Scope to filter by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, RecommendationType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to only pending recommendations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to only approved recommendations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to only positive recommendations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePositive($query)
    {
        return $query->whereIn('type', [
            RecommendationType::Promotion,
            RecommendationType::Recognition,
            RecommendationType::SalaryIncrease,
        ]);
    }

    /**
     * Scope to only corrective recommendations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCorrective($query)
    {
        return $query->whereIn('type', [
            RecommendationType::PerformanceImprovement,
            RecommendationType::Termination,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RecommendationType::class,
            'target_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }
}
