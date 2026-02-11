<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalRatingScale model - Rating definitions (1-5 scale with labels).
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int|null $period_id
 * @property int $value
 * @property string $label
 * @property string|null $description
 * @property string|null $color
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalRatingScale extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_appraisal_rating_scales';

    protected $fillable = [
        'tenant_id',
        'period_id',
        'value',
        'label',
        'description',
        'color',
    ];

    /**
     * Create default rating scales for a period.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function createDefaultScales(?int $periodId = null): \Illuminate\Database\Eloquent\Collection
    {
        $defaults = [
            ['value' => 1, 'label' => 'Unsatisfactory', 'description' => 'Performance consistently fails to meet minimum job requirements.'],
            ['value' => 2, 'label' => 'Below Expectations', 'description' => 'Performance does not fully meet job requirements in some areas.'],
            ['value' => 3, 'label' => 'Meets Expectations', 'description' => 'Performance consistently meets job requirements and expectations.'],
            ['value' => 4, 'label' => 'Exceeds Expectations', 'description' => 'Performance frequently exceeds job requirements.'],
            ['value' => 5, 'label' => 'Outstanding', 'description' => 'Performance consistently exceeds all job requirements with exceptional results.'],
        ];

        $scales = new \Illuminate\Database\Eloquent\Collection();
        foreach ($defaults as $default) {
            $scales->push(self::create([
                'period_id' => $periodId,
                ...$default,
            ]));
        }

        return $scales;
    }

    /**
     * Get the period this rating scale belongs to.
     *
     * @return BelongsTo<AppraisalPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AppraisalPeriod::class, 'period_id');
    }

    /**
     * Get the CSS class for this rating value.
     */
    public function getCssClass(): string
    {
        if ($this->color !== null) {
            return $this->color;
        }

        return match ($this->value) {
            1 => 'bg-red-100 text-red-800',
            2 => 'bg-orange-100 text-orange-800',
            3 => 'bg-yellow-100 text-yellow-800',
            4 => 'bg-blue-100 text-blue-800',
            5 => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
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
     * Scope to order by value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('value');
    }

    /**
     * Scope for global (non-period-specific) scales.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('period_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
