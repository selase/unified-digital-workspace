<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Enums\InterviewStatus;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Interview model - Scheduled interviews.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $application_id
 * @property int|null $stage_id
 * @property string $type
 * @property \Illuminate\Support\Carbon $interview_date
 * @property string $start_time
 * @property string|null $end_time
 * @property string|null $location
 * @property string|null $meeting_link
 * @property InterviewStatus $status
 * @property string|null $instructions
 * @property string|null $feedback_summary
 * @property string|null $overall_rating
 * @property bool|null $is_recommended
 * @property int|null $scheduled_by
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class Interview extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;
    use SoftDeletes;

    protected $table = 'hrms_interviews';

    protected $connection = 'landlord';

    public const TYPE_IN_PERSON = 'in_person';

    public const TYPE_PHONE = 'phone';

    public const TYPE_VIDEO = 'video';

    protected $fillable = [
        'tenant_id',
        'application_id',
        'stage_id',
        'type',
        'interview_date',
        'start_time',
        'end_time',
        'location',
        'meeting_link',
        'status',
        'instructions',
        'feedback_summary',
        'overall_rating',
        'is_recommended',
        'scheduled_by',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'in_person',
        'status' => 'scheduled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_date' => 'date',
            'status' => InterviewStatus::class,
            'is_recommended' => 'boolean',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<InterviewStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(InterviewStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'scheduled_by');
    }

    /**
     * @return HasMany<InterviewPanel, $this>
     */
    public function panelMembers(): HasMany
    {
        return $this->hasMany(InterviewPanel::class);
    }

    /**
     * @return HasMany<InterviewEvaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class);
    }

    public function isScheduled(): bool
    {
        return $this->status === InterviewStatus::Scheduled;
    }

    public function isConfirmed(): bool
    {
        return $this->status === InterviewStatus::Confirmed;
    }

    public function isCompleted(): bool
    {
        return $this->status === InterviewStatus::Completed;
    }

    public function confirm(): bool
    {
        if ($this->status !== InterviewStatus::Scheduled) {
            return false;
        }

        $this->status = InterviewStatus::Confirmed;
        $this->confirmed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function complete(?string $feedbackSummary = null, ?bool $isRecommended = null): bool
    {
        if (! in_array($this->status, [InterviewStatus::Scheduled, InterviewStatus::Confirmed], true)) {
            return false;
        }

        $this->status = InterviewStatus::Completed;
        $this->completed_at = \Illuminate\Support\Carbon::now();
        $this->feedback_summary = $feedbackSummary;
        $this->is_recommended = $isRecommended;

        return $this->save();
    }

    public function cancel(string $reason): bool
    {
        if (! $this->status->canReschedule()) {
            return false;
        }

        $this->status = InterviewStatus::Cancelled;
        $this->cancelled_at = \Illuminate\Support\Carbon::now();
        $this->cancellation_reason = $reason;

        return $this->save();
    }

    public function markNoShow(): bool
    {
        $this->status = InterviewStatus::NoShow;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithStatus($query, InterviewStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [InterviewStatus::Scheduled, InterviewStatus::Confirmed])
            ->where('interview_date', '>=', now()->toDateString())
            ->orderBy('interview_date')
            ->orderBy('start_time');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('interview_date', $date);
    }
}
