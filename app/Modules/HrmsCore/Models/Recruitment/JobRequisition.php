<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Enums\RequisitionStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JobRequisition model - Job opening requests.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $title
 * @property string|null $requisition_number
 * @property int|null $department_id
 * @property int|null $grade_id
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property string|null $job_description
 * @property string|null $requirements
 * @property string|null $responsibilities
 * @property string $employment_type
 * @property int $vacancies
 * @property string|null $min_salary
 * @property string|null $max_salary
 * @property string|null $location
 * @property bool $is_remote
 * @property RequisitionStatus $status
 * @property \Illuminate\Support\Carbon|null $target_start_date
 * @property \Illuminate\Support\Carbon|null $application_deadline
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class JobRequisition extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_job_requisitions';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'title',
        'requisition_number',
        'department_id',
        'grade_id',
        'requested_by',
        'approved_by',
        'job_description',
        'requirements',
        'responsibilities',
        'employment_type',
        'vacancies',
        'min_salary',
        'max_salary',
        'location',
        'is_remote',
        'status',
        'target_start_date',
        'application_deadline',
        'approved_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'employment_type' => 'full_time',
        'vacancies' => 1,
        'is_remote' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'vacancies' => 'integer',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'is_remote' => 'boolean',
            'target_start_date' => 'date',
            'application_deadline' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(JobPosting::class, 'requisition_id');
    }

    public function isDraft(): bool
    {
        return $this->status === RequisitionStatus::Draft;
    }

    public function isOpen(): bool
    {
        return $this->status === RequisitionStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === RequisitionStatus::Closed;
    }

    public function approve(int $approverId): bool
    {
        if ($this->status !== RequisitionStatus::PendingApproval) {
            return false;
        }

        $this->approved_by = $approverId;
        $this->approved_at = \Illuminate\Support\Carbon::now();
        $this->status = RequisitionStatus::Approved;

        return $this->save();
    }

    public function open(): bool
    {
        if ($this->status !== RequisitionStatus::Approved) {
            return false;
        }

        $this->status = RequisitionStatus::Open;

        return $this->save();
    }

    public function close(): bool
    {
        if (! $this->status->isAcceptingApplications()) {
            return false;
        }

        $this->status = RequisitionStatus::Closed;

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, RequisitionStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOpen($query)
    {
        return $query->where('status', RequisitionStatus::Open);
    }
}
