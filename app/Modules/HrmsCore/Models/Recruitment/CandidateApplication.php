<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Enums\ApplicationStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CandidateApplication model - Applications to specific jobs.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $candidate_id
 * @property int $posting_id
 * @property string|null $application_number
 * @property ApplicationStatus $status
 * @property string $stage
 * @property string|null $cover_letter
 * @property string|null $offered_salary
 * @property \Illuminate\Support\Carbon|null $proposed_start_date
 * @property int|null $screened_by
 * @property \Illuminate\Support\Carbon|null $screened_at
 * @property bool|null $is_recommended
 * @property string|null $screening_notes
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property int|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $hired_at
 * @property int|null $hired_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class CandidateApplication extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_candidate_applications';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'candidate_id',
        'posting_id',
        'application_number',
        'status',
        'stage',
        'cover_letter',
        'offered_salary',
        'proposed_start_date',
        'screened_by',
        'screened_at',
        'is_recommended',
        'screening_notes',
        'rejected_at',
        'rejection_reason',
        'rejected_by',
        'hired_at',
        'hired_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
        'stage' => 'application',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'offered_salary' => 'decimal:2',
            'proposed_start_date' => 'date',
            'screened_at' => 'datetime',
            'is_recommended' => 'boolean',
            'rejected_at' => 'datetime',
            'hired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'posting_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function screenedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'screened_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function hiredBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_by');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'application_id');
    }

    /**
     * @return HasMany<CandidateAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(CandidateAssessment::class, 'application_id');
    }

    /**
     * @return HasOne<JobOffer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(JobOffer::class, 'application_id');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function reject(int $rejectedById, string $reason): bool
    {
        $this->status = ApplicationStatus::Rejected;
        $this->rejected_by = $rejectedById;
        $this->rejected_at = \Illuminate\Support\Carbon::now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    public function hire(int $hiredById): bool
    {
        $this->status = ApplicationStatus::Hired;
        $this->hired_by = $hiredById;
        $this->hired_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function shortlist(): bool
    {
        $this->status = ApplicationStatus::Shortlisted;

        return $this->save();
    }

    public function moveToInterview(): bool
    {
        $this->status = ApplicationStatus::Interview;
        $this->stage = 'hr_screening';

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, ApplicationStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            ApplicationStatus::Hired,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPosting($query, int $postingId)
    {
        return $query->where('posting_id', $postingId);
    }
}
