<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Promotion;

use App\Modules\HrmsCore\Enums\PromotionCategory;
use App\Modules\HrmsCore\Enums\PromotionStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * StaffPromotion model - Tracks employee promotions, regradings, and upgrades.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $employee_id
 * @property PromotionCategory $category
 * @property PromotionStatus $status
 * @property int|null $from_grade_id
 * @property int|null $to_grade_id
 * @property int|null $from_salary_level_id
 * @property int|null $to_salary_level_id
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property \Illuminate\Support\Carbon|null $requested_date
 * @property string|null $reason
 * @property string|null $justification
 * @property array<int, string>|null $supporting_documents
 * @property int|null $supervisor_id
 * @property bool|null $supervisor_approved
 * @property string|null $supervisor_comments
 * @property \Illuminate\Support\Carbon|null $supervisor_reviewed_at
 * @property int|null $hr_approver_id
 * @property bool|null $hr_approved
 * @property string|null $hr_comments
 * @property \Illuminate\Support\Carbon|null $hr_reviewed_at
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class StaffPromotion extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_staff_promotions';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'category',
        'status',
        'from_grade_id',
        'to_grade_id',
        'from_salary_level_id',
        'to_salary_level_id',
        'effective_date',
        'requested_date',
        'reason',
        'justification',
        'supporting_documents',
        'supervisor_id',
        'supervisor_approved',
        'supervisor_comments',
        'supervisor_reviewed_at',
        'hr_approver_id',
        'hr_approved',
        'hr_comments',
        'hr_reviewed_at',
        'rejection_reason',
        'completed_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => 'promotion',
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PromotionCategory::class,
            'status' => PromotionStatus::class,
            'effective_date' => 'date',
            'requested_date' => 'date',
            'supporting_documents' => 'array',
            'supervisor_approved' => 'boolean',
            'supervisor_reviewed_at' => 'datetime',
            'hr_approved' => 'boolean',
            'hr_reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ==================== Relationships ====================

    /**
     * Get the employee being promoted.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the employee's current grade.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function fromGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'from_grade_id');
    }

    /**
     * Get the proposed new grade.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function toGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'to_grade_id');
    }

    /**
     * Get the employee's current salary level.
     *
     * @return BelongsTo<SalaryLevel, $this>
     */
    public function fromSalaryLevel(): BelongsTo
    {
        return $this->belongsTo(SalaryLevel::class, 'from_salary_level_id');
    }

    /**
     * Get the proposed new salary level.
     *
     * @return BelongsTo<SalaryLevel, $this>
     */
    public function toSalaryLevel(): BelongsTo
    {
        return $this->belongsTo(SalaryLevel::class, 'to_salary_level_id');
    }

    /**
     * Get the supervisor who reviewed this promotion.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Get the HR approver for this promotion.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hr_approver_id');
    }

    // ==================== Status Methods ====================

    /**
     * Check if the promotion is pending.
     */
    public function isPending(): bool
    {
        return $this->status === PromotionStatus::Pending;
    }

    /**
     * Check if the promotion is awaiting supervisor approval.
     */
    public function isAwaitingSupervisorApproval(): bool
    {
        return $this->status === PromotionStatus::AwaitingSupervisorApproval;
    }

    /**
     * Check if the promotion is awaiting HR approval.
     */
    public function isAwaitingHrApproval(): bool
    {
        return $this->status === PromotionStatus::AwaitingHrApproval;
    }

    /**
     * Check if the promotion is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === PromotionStatus::Approved;
    }

    /**
     * Check if the promotion is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === PromotionStatus::Rejected;
    }

    /**
     * Check if the promotion is in a final state.
     */
    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    // ==================== Category Methods ====================

    /**
     * Check if this is a standard promotion.
     */
    public function isPromotion(): bool
    {
        return $this->category === PromotionCategory::Promotion;
    }

    /**
     * Check if this is a regrading.
     */
    public function isRegrading(): bool
    {
        return $this->category === PromotionCategory::Regrading;
    }

    /**
     * Check if this is an upgrade.
     */
    public function isUpgrade(): bool
    {
        return $this->category === PromotionCategory::Upgrade;
    }

    /**
     * Check if this is a conversion.
     */
    public function isConversion(): bool
    {
        return $this->category === PromotionCategory::Conversion;
    }

    // ==================== Workflow Methods ====================

    /**
     * Submit the promotion for supervisor review.
     */
    public function submitForSupervisorReview(): bool
    {
        if ($this->status !== PromotionStatus::Pending) {
            return false;
        }

        $this->status = PromotionStatus::AwaitingSupervisorApproval;

        return $this->save();
    }

    /**
     * Record supervisor approval.
     */
    public function approveBySupervisor(int $supervisorId, ?string $comments = null): bool
    {
        if ($this->status !== PromotionStatus::AwaitingSupervisorApproval) {
            return false;
        }

        $this->supervisor_id = $supervisorId;
        $this->supervisor_approved = true;
        $this->supervisor_comments = $comments;
        $this->supervisor_reviewed_at = \Illuminate\Support\Carbon::now();
        $this->status = PromotionStatus::AwaitingHrApproval;

        return $this->save();
    }

    /**
     * Record supervisor rejection.
     */
    public function rejectBySupervisor(int $supervisorId, ?string $comments = null): bool
    {
        if ($this->status !== PromotionStatus::AwaitingSupervisorApproval) {
            return false;
        }

        $this->supervisor_id = $supervisorId;
        $this->supervisor_approved = false;
        $this->supervisor_comments = $comments;
        $this->supervisor_reviewed_at = \Illuminate\Support\Carbon::now();
        $this->status = PromotionStatus::Rejected;
        $this->rejection_reason = $comments;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * Record HR approval.
     */
    public function approveByHr(int $hrApproverId, ?string $comments = null): bool
    {
        if ($this->status !== PromotionStatus::AwaitingHrApproval) {
            return false;
        }

        $this->hr_approver_id = $hrApproverId;
        $this->hr_approved = true;
        $this->hr_comments = $comments;
        $this->hr_reviewed_at = \Illuminate\Support\Carbon::now();
        $this->status = PromotionStatus::Approved;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * Record HR rejection.
     */
    public function rejectByHr(int $hrApproverId, string $reason, ?string $comments = null): bool
    {
        if ($this->status !== PromotionStatus::AwaitingHrApproval) {
            return false;
        }

        $this->hr_approver_id = $hrApproverId;
        $this->hr_approved = false;
        $this->hr_comments = $comments;
        $this->hr_reviewed_at = \Illuminate\Support\Carbon::now();
        $this->status = PromotionStatus::Rejected;
        $this->rejection_reason = $reason;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * Check if the promotion can be reviewed by the given employee.
     */
    public function canBeReviewedBy(Employee $reviewer): bool
    {
        return match ($this->status) {
            PromotionStatus::AwaitingSupervisorApproval => $this->supervisor_id === $reviewer->id || $this->supervisor_id === null,
            PromotionStatus::AwaitingHrApproval => true, // HR can review any awaiting HR approval
            default => false,
        };
    }

    /**
     * Get the number of grade levels being promoted.
     */
    public function getGradeLevelChange(): ?int
    {
        if ($this->from_grade_id === null || $this->to_grade_id === null) {
            return null;
        }

        $fromGrade = $this->fromGrade;
        $toGrade = $this->toGrade;

        if ($fromGrade === null || $toGrade === null) {
            return null;
        }

        return $toGrade->sort_order - $fromGrade->sort_order;
    }

    // ==================== Query Scopes ====================

    /**
     * Scope to filter by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, PromotionStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfCategory($query, PromotionCategory $category)
    {
        return $query->where('category', $category);
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
     * Scope to only pending promotions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->whereNotIn('status', [
            PromotionStatus::Approved,
            PromotionStatus::Rejected,
        ]);
    }

    /**
     * Scope to only approved promotions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', PromotionStatus::Approved);
    }

    /**
     * Scope to filter promotions awaiting review by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAwaitingSupervisor($query)
    {
        return $query->where('status', PromotionStatus::AwaitingSupervisorApproval);
    }

    /**
     * Scope to filter promotions awaiting HR review.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAwaitingHr($query)
    {
        return $query->where('status', PromotionStatus::AwaitingHrApproval);
    }

    /**
     * Scope to filter by effective date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeEffectiveBetween($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }
}
