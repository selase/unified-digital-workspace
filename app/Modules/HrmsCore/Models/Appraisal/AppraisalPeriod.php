<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Enums\AppraisalCycle;
use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * AppraisalPeriod model - Define review cycles (Annual 2024, Mid-year Q2, etc.)
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property AppraisalCycle $cycle
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalPeriod extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_periods';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'cycle',
        'start_date',
        'end_date',
        'description',
        'is_active',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (AppraisalPeriod $period): void {
            if (empty($period->slug)) {
                $period->slug = Str::slug($period->name);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle' => AppraisalCycle::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the templates for this period.
     *
     * @return HasMany<AppraisalTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(AppraisalTemplate::class, 'period_id');
    }

    /**
     * Get the appraisals for this period.
     *
     * @return HasMany<Appraisal, $this>
     */
    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'period_id');
    }

    /**
     * Get the rating scales for this period.
     *
     * @return HasMany<AppraisalRatingScale, $this>
     */
    public function ratingScales(): HasMany
    {
        return $this->hasMany(AppraisalRatingScale::class, 'period_id');
    }

    /**
     * Check if the period is currently active (within date range).
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        return $this->start_date->lte($today) && $this->end_date->gte($today);
    }

    /**
     * Check if the period has ended.
     */
    public function hasEnded(): bool
    {
        return $this->end_date->lt(now()->startOfDay());
    }

    /**
     * Check if the period has not started yet.
     */
    public function hasNotStarted(): bool
    {
        return $this->start_date->gt(now()->startOfDay());
    }

    /**
     * Scope to only active periods.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to currently active periods (within date range).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCurrentlyActive($query)
    {
        $today = now()->startOfDay();

        return $query->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    /**
     * Scope to filter by cycle type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForCycle($query, AppraisalCycle $cycle)
    {
        return $query->where('cycle', $cycle);
    }
}
