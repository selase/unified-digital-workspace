<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AppraisalSection model - Sections within templates (Performance, Competencies, Goals).
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $template_id
 * @property string $name
 * @property string|null $description
 * @property string $weight
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalSection extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_sections';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'name',
        'description',
        'weight',
        'sort_order',
        'is_active',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'weight' => 0,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * Get the template this section belongs to.
     *
     * @return BelongsTo<AppraisalTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AppraisalTemplate::class, 'template_id');
    }

    /**
     * Get the criteria for this section.
     *
     * @return HasMany<AppraisalCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(AppraisalCriterion::class, 'section_id')->orderBy('sort_order');
    }

    /**
     * Get the scores for this section.
     *
     * @return HasMany<AppraisalScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AppraisalScore::class, 'section_id');
    }

    /**
     * Get the total weight of all criteria in this section.
     */
    public function getTotalCriteriaWeight(): float
    {
        return (float) $this->criteria()->sum('weight');
    }

    /**
     * Get the required criteria count.
     */
    public function getRequiredCriteriaCount(): int
    {
        return $this->criteria()->where('is_required', true)->count();
    }

    /**
     * Scope to only active sections.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Scope to filter by template.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForTemplate($query, int $templateId)
    {
        return $query->where('template_id', $templateId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
