<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * AppraisalTemplate model - Reusable templates with sections and criteria.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int|null $period_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalTemplate extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_templates';

    protected $fillable = [
        'tenant_id',
        'period_id',
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * Get the period this template belongs to.
     *
     * @return BelongsTo<AppraisalPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AppraisalPeriod::class, 'period_id');
    }

    /**
     * Get the sections for this template.
     *
     * @return HasMany<AppraisalSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(AppraisalSection::class, 'template_id')->orderBy('sort_order');
    }

    /**
     * Get the appraisals using this template.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'template_id');
    }

    /**
     * Get all criteria through sections.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AppraisalCriterion>
     */
    public function getAllCriteria(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->sections()
            ->with('criteria')
            ->get()
            ->flatMap(fn (AppraisalSection $section) => $section->criteria);
    }

    /**
     * Get the total weight of all sections.
     */
    public function getTotalSectionWeight(): float
    {
        return (float) $this->sections()->sum('weight');
    }

    /**
     * Scope to only active templates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only default templates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to order by sort order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope to filter by period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('period_id', $periodId);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (AppraisalTemplate $template): void {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
