<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CandidateAssessment model - Tests and evaluations.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $application_id
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string|null $score
 * @property string|null $max_score
 * @property string|null $passing_score
 * @property bool|null $is_passed
 * @property array<string, mixed>|null $results
 * @property string|null $feedback
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|null $evaluated_by
 * @property \Illuminate\Support\Carbon|null $evaluated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CandidateAssessment extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    public const TYPE_TECHNICAL = 'technical';

    public const TYPE_APTITUDE = 'aptitude';

    public const TYPE_PERSONALITY = 'personality';

    public const TYPE_SKILLS = 'skills';

    public const TYPE_LANGUAGE = 'language';

    protected $table = 'hrms_candidate_assessments';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'application_id',
        'type',
        'name',
        'description',
        'status',
        'score',
        'max_score',
        'passing_score',
        'is_passed',
        'results',
        'feedback',
        'assigned_at',
        'started_at',
        'completed_at',
        'expires_at',
        'evaluated_by',
        'evaluated_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_TECHNICAL => 'Technical',
            self::TYPE_APTITUDE => 'Aptitude',
            self::TYPE_PERSONALITY => 'Personality',
            self::TYPE_SKILLS => 'Skills',
            self::TYPE_LANGUAGE => 'Language',
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
     * @return BelongsTo<Employee, $this>
     */
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluated_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->expires_at !== null && $this->expires_at->isPast());
    }

    public function start(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->status = 'in_progress';
        $this->started_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function complete(float $score): bool
    {
        $this->status = 'completed';
        $this->score = (string) $score;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        if ($this->passing_score !== null) {
            $this->is_passed = $score >= (float) $this->passing_score;
        }

        return $this->save();
    }

    public function getScorePercentage(): ?float
    {
        if ($this->score === null || $this->max_score === null || (float) $this->max_score === 0.0) {
            return null;
        }

        return ((float) $this->score / (float) $this->max_score) * 100;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
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
    public function scopePassed($query)
    {
        return $query->where('is_passed', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'is_passed' => 'boolean',
            'results' => 'array',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }
}
