<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalResponse model - Employee responses to each criterion.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property int $criterion_id
 * @property int|null $self_rating
 * @property string|null $self_comments
 * @property int|null $supervisor_rating
 * @property string|null $supervisor_comments
 * @property int|null $final_rating
 * @property string|null $final_comments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalResponse extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_responses';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'criterion_id',
        'self_rating',
        'self_comments',
        'supervisor_rating',
        'supervisor_comments',
        'final_rating',
        'final_comments',
    ];

    /**
     * Get the appraisal this response belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * Get the criterion this response is for.
     *
     * @return BelongsTo<AppraisalCriterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AppraisalCriterion::class, 'criterion_id');
    }

    /**
     * Check if self-assessment is complete.
     */
    public function hasSelfAssessment(): bool
    {
        return $this->self_rating !== null;
    }

    /**
     * Check if supervisor assessment is complete.
     */
    public function hasSupervisorAssessment(): bool
    {
        return $this->supervisor_rating !== null;
    }

    /**
     * Check if final assessment is complete.
     */
    public function hasFinalAssessment(): bool
    {
        return $this->final_rating !== null;
    }

    /**
     * Get the effective rating (final, supervisor, or self).
     */
    public function getEffectiveRating(): ?int
    {
        return $this->final_rating ?? $this->supervisor_rating ?? $this->self_rating;
    }

    /**
     * Get the rating difference between self and supervisor.
     */
    public function getRatingDifference(): ?int
    {
        if ($this->self_rating === null || $this->supervisor_rating === null) {
            return null;
        }

        return $this->supervisor_rating - $this->self_rating;
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
     * Scope to filter by criterion.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForCriterion($query, int $criterionId)
    {
        return $query->where('criterion_id', $criterionId);
    }

    /**
     * Scope to responses with self-assessment.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithSelfAssessment($query)
    {
        return $query->whereNotNull('self_rating');
    }

    /**
     * Scope to responses with supervisor assessment.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithSupervisorAssessment($query)
    {
        return $query->whereNotNull('supervisor_rating');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_rating' => 'integer',
            'supervisor_rating' => 'integer',
            'final_rating' => 'integer',
        ];
    }
}
