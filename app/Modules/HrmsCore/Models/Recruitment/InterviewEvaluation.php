<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InterviewEvaluation model - Panel member evaluations.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $interview_id
 * @property int $evaluator_id
 * @property array<string, mixed>|null $criteria_scores
 * @property int|null $overall_score
 * @property string|null $overall_rating
 * @property string|null $strengths
 * @property string|null $weaknesses
 * @property string|null $comments
 * @property bool|null $is_recommended
 * @property string|null $recommendation_notes
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class InterviewEvaluation extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_interview_evaluations';

    protected $fillable = [
        'tenant_id',
        'interview_id',
        'evaluator_id',
        'criteria_scores',
        'overall_score',
        'overall_rating',
        'strengths',
        'weaknesses',
        'comments',
        'is_recommended',
        'recommendation_notes',
        'submitted_at',
    ];

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluator_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function submit(): bool
    {
        $this->submitted_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('submitted_at');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->whereNull('submitted_at');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criteria_scores' => 'array',
            'overall_score' => 'integer',
            'is_recommended' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }
}
