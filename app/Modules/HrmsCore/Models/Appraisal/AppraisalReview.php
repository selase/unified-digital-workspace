<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalReview model - Supervisor/HOD/HR review records.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property int $reviewer_id
 * @property string $reviewer_type
 * @property string|null $overall_rating
 * @property string|null $strengths
 * @property string|null $areas_for_improvement
 * @property string|null $general_comments
 * @property string|null $decision
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalReview extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    public const TYPE_SUPERVISOR = 'supervisor';

    public const TYPE_HOD = 'hod';

    public const TYPE_HR = 'hr';

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REVISION_REQUESTED = 'revision_requested';

    public const DECISION_REJECTED = 'rejected';

    protected $table = 'hrms_appraisal_reviews';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'reviewer_id',
        'reviewer_type',
        'overall_rating',
        'strengths',
        'areas_for_improvement',
        'general_comments',
        'decision',
        'reviewed_at',
    ];

    /**
     * Get available reviewer types.
     *
     * @return array<string, string>
     */
    public static function reviewerTypes(): array
    {
        return [
            self::TYPE_SUPERVISOR => 'Supervisor',
            self::TYPE_HOD => 'Head of Department',
            self::TYPE_HR => 'Human Resources',
        ];
    }

    /**
     * Get available decisions.
     *
     * @return array<string, string>
     */
    public static function decisions(): array
    {
        return [
            self::DECISION_APPROVED => 'Approved',
            self::DECISION_REVISION_REQUESTED => 'Revision Requested',
            self::DECISION_REJECTED => 'Rejected',
        ];
    }

    /**
     * Get the appraisal this review belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * Get the reviewer.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    /**
     * Check if this is a supervisor review.
     */
    public function isSupervisorReview(): bool
    {
        return $this->reviewer_type === self::TYPE_SUPERVISOR;
    }

    /**
     * Check if this is a HOD review.
     */
    public function isHodReview(): bool
    {
        return $this->reviewer_type === self::TYPE_HOD;
    }

    /**
     * Check if this is an HR review.
     */
    public function isHrReview(): bool
    {
        return $this->reviewer_type === self::TYPE_HR;
    }

    /**
     * Check if the review is complete.
     */
    public function isComplete(): bool
    {
        return $this->reviewed_at !== null;
    }

    /**
     * Check if the decision is approved.
     */
    public function isApproved(): bool
    {
        return $this->decision === self::DECISION_APPROVED;
    }

    /**
     * Check if revision was requested.
     */
    public function isRevisionRequested(): bool
    {
        return $this->decision === self::DECISION_REVISION_REQUESTED;
    }

    /**
     * Mark the review as complete.
     */
    public function markComplete(): void
    {
        $this->reviewed_at = \Illuminate\Support\Carbon::now();
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
     * Scope to filter by reviewer type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('reviewer_type', $type);
    }

    /**
     * Scope to only completed reviews.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull('reviewed_at');
    }

    /**
     * Scope to only pending reviews.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->whereNull('reviewed_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_rating' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }
}
