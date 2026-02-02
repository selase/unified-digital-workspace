<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Enums\OfferStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Salary\SalaryLevel;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * JobOffer model - Offers made to candidates.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $application_id
 * @property string|null $offer_number
 * @property string $position_title
 * @property int|null $department_id
 * @property int|null $grade_id
 * @property int|null $salary_level_id
 * @property string $employment_type
 * @property string|null $offered_salary
 * @property array<string, mixed>|null $benefits
 * @property string|null $additional_terms
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $offer_valid_until
 * @property OfferStatus $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string|null $candidate_decision
 * @property string|null $candidate_feedback
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class JobOffer extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_job_offers';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'application_id',
        'offer_number',
        'position_title',
        'department_id',
        'grade_id',
        'salary_level_id',
        'employment_type',
        'offered_salary',
        'benefits',
        'additional_terms',
        'start_date',
        'offer_valid_until',
        'status',
        'approved_by',
        'approved_at',
        'sent_at',
        'candidate_decision',
        'candidate_feedback',
        'responded_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'employment_type' => 'full_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'offered_salary' => 'decimal:2',
            'benefits' => 'array',
            'start_date' => 'date',
            'offer_valid_until' => 'date',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CandidateApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
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
     * @return BelongsTo<SalaryLevel, $this>
     */
    public function salaryLevel(): BelongsTo
    {
        return $this->belongsTo(SalaryLevel::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * @return HasMany<OfferNegotiation, $this>
     */
    public function negotiations(): HasMany
    {
        return $this->hasMany(OfferNegotiation::class, 'offer_id');
    }

    public function isDraft(): bool
    {
        return $this->status === OfferStatus::Draft;
    }

    public function isSent(): bool
    {
        return $this->status === OfferStatus::Sent;
    }

    public function isAccepted(): bool
    {
        return $this->status === OfferStatus::Accepted;
    }

    public function isRejected(): bool
    {
        return $this->status === OfferStatus::Rejected;
    }

    public function isExpired(): bool
    {
        return $this->status === OfferStatus::Expired ||
            ($this->offer_valid_until !== null && $this->offer_valid_until->isPast());
    }

    public function approve(int $approverId): bool
    {
        if ($this->status !== OfferStatus::PendingApproval) {
            return false;
        }

        $this->approved_by = $approverId;
        $this->approved_at = \Illuminate\Support\Carbon::now();
        $this->status = OfferStatus::Sent;
        $this->sent_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function send(): bool
    {
        if (! in_array($this->status, [OfferStatus::Draft, OfferStatus::PendingApproval], true)) {
            return false;
        }

        $this->status = OfferStatus::Sent;
        $this->sent_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function accept(?string $feedback = null): bool
    {
        if (! $this->status->awaitingResponse()) {
            return false;
        }

        $this->status = OfferStatus::Accepted;
        $this->candidate_decision = 'accept';
        $this->candidate_feedback = $feedback;
        $this->responded_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function reject(string $feedback): bool
    {
        if (! $this->status->awaitingResponse()) {
            return false;
        }

        $this->status = OfferStatus::Rejected;
        $this->candidate_decision = 'reject';
        $this->candidate_feedback = $feedback;
        $this->responded_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function withdraw(): bool
    {
        if ($this->status->isFinal()) {
            return false;
        }

        $this->status = OfferStatus::Withdrawn;

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, OfferStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [OfferStatus::Draft, OfferStatus::PendingApproval, OfferStatus::Sent]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', OfferStatus::Accepted);
    }
}
