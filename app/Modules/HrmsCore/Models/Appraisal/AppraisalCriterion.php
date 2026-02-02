<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AppraisalCriterion model - Individual criteria to rate within sections.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $section_id
 * @property string $name
 * @property string|null $description
 * @property string $weight
 * @property bool $is_required
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalCriterion extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_criteria';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'section_id',
        'name',
        'description',
        'weight',
        'is_required',
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
        'is_required' => true,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the section this criterion belongs to.
     *
     * @return BelongsTo<AppraisalSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(AppraisalSection::class, 'section_id');
    }

    /**
     * Get the responses for this criterion.
     *
     * @return HasMany<AppraisalResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(AppraisalResponse::class, 'criterion_id');
    }

    /**
     * Get the template through the section.
     */
    public function getTemplate(): ?AppraisalTemplate
    {
        return $this->section?->template;
    }

    /**
     * Scope to only active criteria.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only required criteria.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
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
     * Scope to filter by section.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForSection($query, int $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }
}
