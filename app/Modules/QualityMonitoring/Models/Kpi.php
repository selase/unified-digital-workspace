<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Kpi extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_kpis';

    protected $fillable = [
        'tenant_id',
        'activity_id',
        'indicator_id',
        'name',
        'unit',
        'target_value',
        'baseline_value',
        'direction',
        'calculation',
        'frequency',
    ];

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<Indicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    /**
     * @return HasMany<KpiUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(KpiUpdate::class);
    }

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'baseline_value' => 'decimal:2',
            'calculation' => 'array',
        ];
    }
}
