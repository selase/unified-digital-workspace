<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Leave;

use App\Modules\HrmsCore\Database\Factories\LeaveRequestFactory;
use App\Modules\HrmsCore\Enums\LeaveStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LeaveRequest model - Employee leave requests with approval workflow.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $employee_id
 * @property int $leave_category_id
 * @property \Illuminate\Support\Carbon $proposed_start_date
 * @property \Illuminate\Support\Carbon $proposed_end_date
 * @property int $no_requested_days
 * @property string|null $leave_reasons
 * @property string|null $contact_when_away
 * @property int|null $supervisor_id
 * @property int|null $hod_id
 * @property int|null $hr_verifier_id
 * @property int|null $relieving_officer_id
 * @property int|null $no_recommended_days
 * @property \Illuminate\Support\Carbon|null $recommended_start_date
 * @property \Illuminate\Support\Carbon|null $recommended_end_date
 * @property string|null $supervisor_comments
 * @property \Illuminate\Support\Carbon|null $supervisor_verified_at
 * @property string|null $hr_comments
 * @property \Illuminate\Support\Carbon|null $hr_verified_at
 * @property LeaveStatus $status
 * @property string|null $hr_verification_status
 * @property int|null $no_of_days_approved
 * @property \Illuminate\Support\Carbon|null $approved_start_date
 * @property \Illuminate\Support\Carbon|null $approved_end_date
 * @property string|null $hod_comments
 * @property \Illuminate\Support\Carbon|null $hod_decision_at
 * @property \Illuminate\Support\Carbon|null $resumption_date
 * @property int $no_of_holidays_in_period
 * @property int $no_of_weekends_in_period
 * @property bool $is_recalled
 * @property \Illuminate\Support\Carbon|null $recall_date
 * @property int|null $no_of_days_recalled
 * @property string|null $recall_reason
 * @property \Illuminate\Support\Carbon|null $recalled_at
 * @property int|null $balance_at_request
 * @property int|null $carry_forward_at_request
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @use HasFactory<LeaveRequestFactory>
 */
final class LeaveRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    use HasHrmsUuid;
    use SoftDeletes;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return LeaveRequestFactory::new();
    }

    protected $table = 'hrms_leave_requests';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'leave_category_id',
        'proposed_start_date',
        'proposed_end_date',
        'no_requested_days',
        'leave_reasons',
        'contact_when_away',
        'supervisor_id',
        'hod_id',
        'hr_verifier_id',
        'relieving_officer_id',
        'no_recommended_days',
        'recommended_start_date',
        'recommended_end_date',
        'supervisor_comments',
        'supervisor_verified_at',
        'hr_comments',
        'hr_verified_at',
        'status',
        'hr_verification_status',
        'no_of_days_approved',
        'approved_start_date',
        'approved_end_date',
        'hod_comments',
        'hod_decision_at',
        'resumption_date',
        'no_of_holidays_in_period',
        'no_of_weekends_in_period',
        'is_recalled',
        'recall_date',
        'no_of_days_recalled',
        'recall_reason',
        'recalled_at',
        'balance_at_request',
        'carry_forward_at_request',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'no_of_holidays_in_period' => 0,
        'no_of_weekends_in_period' => 0,
        'is_recalled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_start_date' => 'date',
            'proposed_end_date' => 'date',
            'no_requested_days' => 'integer',
            'no_recommended_days' => 'integer',
            'recommended_start_date' => 'date',
            'recommended_end_date' => 'date',
            'supervisor_verified_at' => 'datetime',
            'hr_verified_at' => 'datetime',
            'status' => LeaveStatus::class,
            'no_of_days_approved' => 'integer',
            'approved_start_date' => 'date',
            'approved_end_date' => 'date',
            'hod_decision_at' => 'datetime',
            'resumption_date' => 'date',
            'no_of_holidays_in_period' => 'integer',
            'no_of_weekends_in_period' => 'integer',
            'is_recalled' => 'boolean',
            'recall_date' => 'date',
            'no_of_days_recalled' => 'integer',
            'recalled_at' => 'datetime',
            'balance_at_request' => 'integer',
            'carry_forward_at_request' => 'integer',
        ];
    }

    /**
     * Get the employee who made the request.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave category.
     *
     * @return BelongsTo<LeaveCategory, $this>
     */
    public function leaveCategory(): BelongsTo
    {
        return $this->belongsTo(LeaveCategory::class);
    }

    /**
     * Get the supervisor who verified the request.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Get the HOD who made the final decision.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function hod(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hod_id');
    }

    /**
     * Get the HR staff who verified the request.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function hrVerifier(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hr_verifier_id');
    }

    /**
     * Get the relieving officer.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function relievingOfficer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'relieving_officer_id');
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    /**
     * Check if the request has been verified by supervisor.
     */
    public function isVerified(): bool
    {
        return $this->status === LeaveStatus::Verified;
    }

    /**
     * Check if the request has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === LeaveStatus::Approved;
    }

    /**
     * Check if the request has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === LeaveStatus::Rejected;
    }

    /**
     * Check if the request has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === LeaveStatus::Cancelled;
    }

    /**
     * Check if the request has been recalled.
     */
    public function isRecalled(): bool
    {
        return $this->status === LeaveStatus::Recalled || $this->is_recalled;
    }

    /**
     * Check if the request can be cancelled by the employee.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [LeaveStatus::Pending, LeaveStatus::Verified], true);
    }

    /**
     * Check if the request can be recalled.
     */
    public function canBeRecalled(): bool
    {
        return $this->status === LeaveStatus::Approved && ! $this->is_recalled;
    }

    /**
     * Get the effective start date (approved or proposed).
     */
    public function getEffectiveStartDate(): \Carbon\CarbonInterface
    {
        return $this->approved_start_date ?? $this->proposed_start_date;
    }

    /**
     * Get the effective end date (approved or proposed).
     */
    public function getEffectiveEndDate(): \Carbon\CarbonInterface
    {
        return $this->approved_end_date ?? $this->proposed_end_date;
    }

    /**
     * Get the effective number of days (approved or requested).
     */
    public function getEffectiveDays(): int
    {
        return $this->no_of_days_approved ?? $this->no_requested_days;
    }

    /**
     * Scope to only pending requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    /**
     * Scope to only verified requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeVerified($query)
    {
        return $query->where('status', LeaveStatus::Verified);
    }

    /**
     * Scope to only approved requests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeApproved($query)
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    /**
     * Scope to requests for a specific employee.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to requests awaiting supervisor verification.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAwaitingSupervisorVerification($query)
    {
        return $query->where('status', LeaveStatus::Pending)
            ->whereNull('supervisor_verified_at');
    }

    /**
     * Scope to requests awaiting HOD decision.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAwaitingHodDecision($query)
    {
        return $query->where('status', LeaveStatus::Verified)
            ->whereNull('hod_decision_at');
    }
}
