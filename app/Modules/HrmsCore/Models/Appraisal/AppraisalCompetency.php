<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalCompetency model - Competency assessments.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property string $name
 * @property string|null $description
 * @property string|null $behavioral_indicators
 * @property int|null $self_rating
 * @property string|null $self_evidence
 * @property int|null $supervisor_rating
 * @property string|null $supervisor_evidence
 * @property int|null $final_rating
 * @property string $weight
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalCompetency extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_competencies';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'name',
        'description',
        'behavioral_indicators',
        'self_rating',
        'self_evidence',
        'supervisor_rating',
        'supervisor_evidence',
        'final_rating',
        'weight',
        'sort_order',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'weight' => 0,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_rating' => 'integer',
            'supervisor_rating' => 'integer',
            'final_rating' => 'integer',
            'weight' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the appraisal this competency belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
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
     * Get the effective rating (final, supervisor, or self).
     */
    public function getEffectiveRating(): ?int
    {
        return $this->final_rating ?? $this->supervisor_rating ?? $this->self_rating;
    }

    /**
     * Get the weighted score.
     */
    public function getWeightedScore(): float
    {
        $rating = $this->getEffectiveRating();

        if ($rating === null || (float) $this->weight === 0.0) {
            return 0.0;
        }

        return ($rating / 5) * (float) $this->weight;
    }

    /**
     * Get the rating gap between self and supervisor.
     */
    public function getRatingGap(): ?int
    {
        if ($this->self_rating === null || $this->supervisor_rating === null) {
            return null;
        }

        return abs($this->supervisor_rating - $this->self_rating);
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
     * Scope to order by sort order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to competencies with significant rating gaps.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithSignificantGap($query, int $threshold = 2)
    {
        return $query->whereNotNull('self_rating')
            ->whereNotNull('supervisor_rating')
            ->whereRaw('ABS(supervisor_rating - self_rating) >= ?', [$threshold]);
    }
}
