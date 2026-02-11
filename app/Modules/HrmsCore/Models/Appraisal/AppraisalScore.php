<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalScore model - Calculated scores per section/overall.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property int|null $section_id
 * @property string|null $self_score
 * @property string|null $supervisor_score
 * @property string|null $final_score
 * @property string|null $weighted_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalScore extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_scores';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'section_id',
        'self_score',
        'supervisor_score',
        'final_score',
        'weighted_score',
    ];

    /**
     * Get the appraisal this score belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * Get the section this score is for.
     *
     * @return BelongsTo<AppraisalSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(AppraisalSection::class, 'section_id');
    }

    /**
     * Check if this is an overall score (no section).
     */
    public function isOverallScore(): bool
    {
        return $this->section_id === null;
    }

    /**
     * Check if this is a section score.
     */
    public function isSectionScore(): bool
    {
        return $this->section_id !== null;
    }

    /**
     * Get the effective score (final, supervisor, or self).
     */
    public function getEffectiveScore(): float
    {
        if ($this->final_score !== null) {
            return (float) $this->final_score;
        }

        if ($this->supervisor_score !== null) {
            return (float) $this->supervisor_score;
        }

        return (float) ($this->self_score ?? 0);
    }

    /**
     * Get the score gap between self and supervisor.
     */
    public function getScoreGap(): ?float
    {
        if ($this->self_score === null || $this->supervisor_score === null) {
            return null;
        }

        return abs((float) $this->supervisor_score - (float) $this->self_score);
    }

    /**
     * Calculate the weighted score based on section weight.
     */
    public function calculateWeightedScore(): float
    {
        $effectiveScore = $this->getEffectiveScore();

        if ($this->section === null) {
            return $effectiveScore;
        }

        $weight = (float) $this->section->weight;

        if ($weight === 0.0) {
            return 0.0;
        }

        return ($effectiveScore / 5) * ($weight / 100);
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
     * Scope to filter by section.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForSection($query, int $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    /**
     * Scope to only overall scores.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOverall($query)
    {
        return $query->whereNull('section_id');
    }

    /**
     * Scope to only section scores.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSections($query)
    {
        return $query->whereNotNull('section_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_score' => 'decimal:2',
            'supervisor_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'weighted_score' => 'decimal:2',
        ];
    }
}
