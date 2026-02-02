<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Enums\AppraisalStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Appraisal model - Main appraisal record linking employee to period/template.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $employee_id
 * @property int $period_id
 * @property int $template_id
 * @property AppraisalStatus $status
 * @property int|null $supervisor_id
 * @property int|null $hod_id
 * @property int|null $hr_reviewer_id
 * @property \Illuminate\Support\Carbon|null $self_assessment_submitted_at
 * @property \Illuminate\Support\Carbon|null $supervisor_reviewed_at
 * @property \Illuminate\Support\Carbon|null $hod_reviewed_at
 * @property \Illuminate\Support\Carbon|null $hr_reviewed_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $self_overall_score
 * @property string|null $supervisor_overall_score
 * @property string|null $final_overall_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class Appraisal extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_appraisals';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'period_id',
        'template_id',
        'status',
        'supervisor_id',
        'hod_id',
        'hr_reviewer_id',
        'self_assessment_submitted_at',
        'supervisor_reviewed_at',
        'hod_reviewed_at',
        'hr_reviewed_at',
        'completed_at',
        'self_overall_score',
        'supervisor_overall_score',
        'final_overall_score',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AppraisalStatus::class,
            'self_assessment_submitted_at' => 'datetime',
            'supervisor_reviewed_at' => 'datetime',
            'hod_reviewed_at' => 'datetime',
            'hr_reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'self_overall_score' => 'decimal:2',
            'supervisor_overall_score' => 'decimal:2',
            'final_overall_score' => 'decimal:2',
        ];
    }

    /**
     * Get the employee being appraised.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the appraisal period.
     *
     * @return BelongsTo<AppraisalPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AppraisalPeriod::class, 'period_id');
    }

    /**
     * Get the appraisal template.
     *
     * @return BelongsTo<AppraisalTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplate::class, 'template_id');
    }

    /**
     * Get the supervisor reviewer.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Get the HOD reviewer.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function hod(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hod_id');
    }

    /**
     * Get the HR reviewer.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hr_reviewer_id');
    }

    /**
     * Get the responses for this appraisal.
     *
     * @return HasMany<AppraisalResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(AppraisalResponse::class);
    }

    /**
     * Get the goals for this appraisal.
     *
     * @return HasMany<AppraisalGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(AppraisalGoal::class);
    }

    /**
     * Get the competencies for this appraisal.
     *
     * @return HasMany<AppraisalCompetency, $this>
     */
    public function competencies(): HasMany
    {
        return $this->hasMany(AppraisalCompetency::class);
    }

    /**
     * Get the reviews for this appraisal.
     *
     * @return HasMany<AppraisalReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(AppraisalReview::class);
    }

    /**
     * Get the comments for this appraisal.
     *
     * @return HasMany<AppraisalComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(AppraisalComment::class);
    }

    /**
     * Get the scores for this appraisal.
     *
     * @return HasMany<AppraisalScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AppraisalScore::class);
    }

    /**
     * Get the recommendations for this appraisal.
     *
     * @return HasMany<AppraisalRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(AppraisalRecommendation::class);
    }

    // ==================== Workflow Methods ====================

    /**
     * Check if the appraisal is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === AppraisalStatus::Draft;
    }

    /**
     * Check if the appraisal is in self-assessment phase.
     */
    public function isInSelfAssessment(): bool
    {
        return $this->status === AppraisalStatus::SelfAssessment;
    }

    /**
     * Check if the appraisal is awaiting supervisor review.
     */
    public function isAwaitingSupervisorReview(): bool
    {
        return $this->status === AppraisalStatus::SupervisorReview;
    }

    /**
     * Check if the appraisal is awaiting HOD review.
     */
    public function isAwaitingHodReview(): bool
    {
        return $this->status === AppraisalStatus::HodReview;
    }

    /**
     * Check if the appraisal is awaiting HR review.
     */
    public function isAwaitingHrReview(): bool
    {
        return $this->status === AppraisalStatus::HrReview;
    }

    /**
     * Check if the appraisal is complete.
     */
    public function isComplete(): bool
    {
        return $this->status === AppraisalStatus::Complete;
    }

    /**
     * Check if the appraisal is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === AppraisalStatus::Cancelled;
    }

    /**
     * Check if the appraisal can accept self-assessment submission.
     */
    public function canSubmitSelfAssessment(): bool
    {
        return $this->status === AppraisalStatus::SelfAssessment;
    }

    /**
     * Check if a given employee can review this appraisal.
     */
    public function canReview(Employee $reviewer): bool
    {
        return match ($this->status) {
            AppraisalStatus::SupervisorReview => $this->supervisor_id === $reviewer->id,
            AppraisalStatus::HodReview => $this->hod_id === $reviewer->id,
            AppraisalStatus::HrReview => $this->hr_reviewer_id === $reviewer->id,
            default => false,
        };
    }

    /**
     * Transition the appraisal to a new status.
     */
    public function transitionTo(AppraisalStatus $status): bool
    {
        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        // Update relevant timestamps
        $now = \Illuminate\Support\Carbon::now();
        match ($status) {
            AppraisalStatus::SupervisorReview => $this->self_assessment_submitted_at = $now,
            AppraisalStatus::HodReview => $this->supervisor_reviewed_at = $now,
            AppraisalStatus::HrReview => $this->hod_reviewed_at = $now,
            AppraisalStatus::Complete => $this->hr_reviewed_at = $now,
            default => null,
        };

        if ($status === AppraisalStatus::Complete) {
            $this->completed_at = $now;
        }

        return $this->save();
    }

    /**
     * Start the self-assessment phase.
     */
    public function startSelfAssessment(): bool
    {
        return $this->transitionTo(AppraisalStatus::SelfAssessment);
    }

    /**
     * Submit the self-assessment.
     */
    public function submitSelfAssessment(): bool
    {
        return $this->transitionTo(AppraisalStatus::SupervisorReview);
    }

    /**
     * Complete supervisor review.
     */
    public function completeSupervisorReview(): bool
    {
        return $this->transitionTo(AppraisalStatus::HodReview);
    }

    /**
     * Complete HOD review.
     */
    public function completeHodReview(): bool
    {
        return $this->transitionTo(AppraisalStatus::HrReview);
    }

    /**
     * Complete HR review and finalize the appraisal.
     */
    public function completeHrReview(): bool
    {
        return $this->transitionTo(AppraisalStatus::Complete);
    }

    /**
     * Cancel the appraisal.
     */
    public function cancel(): bool
    {
        return $this->transitionTo(AppraisalStatus::Cancelled);
    }

    // ==================== Score Calculation Methods ====================

    /**
     * Calculate and return the self-assessment overall score.
     */
    public function calculateSelfScore(): float
    {
        $responses = $this->responses()->whereNotNull('self_rating')->get();

        if ($responses->isEmpty()) {
            return 0.0;
        }

        return round($responses->avg('self_rating'), 2);
    }

    /**
     * Calculate and return the supervisor overall score.
     */
    public function calculateSupervisorScore(): float
    {
        $responses = $this->responses()->whereNotNull('supervisor_rating')->get();

        if ($responses->isEmpty()) {
            return 0.0;
        }

        return round($responses->avg('supervisor_rating'), 2);
    }

    /**
     * Calculate and return the final overall score.
     */
    public function calculateFinalScore(): float
    {
        $responses = $this->responses()->whereNotNull('final_rating')->get();

        if ($responses->isEmpty()) {
            // Fall back to supervisor score if no final ratings
            return $this->calculateSupervisorScore();
        }

        return round($responses->avg('final_rating'), 2);
    }

    /**
     * Get the overall score (final, supervisor, or self in that order of preference).
     */
    public function getOverallScore(): float
    {
        if ($this->final_overall_score !== null) {
            return (float) $this->final_overall_score;
        }

        if ($this->supervisor_overall_score !== null) {
            return (float) $this->supervisor_overall_score;
        }

        if ($this->self_overall_score !== null) {
            return (float) $this->self_overall_score;
        }

        return 0.0;
    }

    // ==================== Query Scopes ====================

    /**
     * Scope to filter by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, AppraisalStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by employee.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('period_id', $periodId);
    }

    /**
     * Scope to only pending appraisals (not complete or cancelled).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->whereNotIn('status', [
            AppraisalStatus::Complete,
            AppraisalStatus::Cancelled,
        ]);
    }

    /**
     * Scope to only completed appraisals.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeComplete($query)
    {
        return $query->where('status', AppraisalStatus::Complete);
    }

    /**
     * Scope to appraisals awaiting review by a specific employee.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAwaitingReviewBy($query, int $employeeId)
    {
        return $query->where(function ($q) use ($employeeId): void {
            $q->where(function ($sq) use ($employeeId): void {
                $sq->where('status', AppraisalStatus::SupervisorReview)
                    ->where('supervisor_id', $employeeId);
            })->orWhere(function ($sq) use ($employeeId): void {
                $sq->where('status', AppraisalStatus::HodReview)
                    ->where('hod_id', $employeeId);
            })->orWhere(function ($sq) use ($employeeId): void {
                $sq->where('status', AppraisalStatus::HrReview)
                    ->where('hr_reviewer_id', $employeeId);
            });
        });
    }
}
