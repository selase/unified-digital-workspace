<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Activity extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_activities';

    protected $fillable = [
        'tenant_id',
        'objective_id',
        'title',
        'description',
        'responsible_id',
        'start_date',
        'due_date',
        'status',
        'weight',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Objective, $this>
     */
    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    /**
     * @return HasMany<Kpi, $this>
     */
    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    /**
     * @return HasMany<Variance, $this>
     */
    public function variances(): HasMany
    {
        return $this->hasMany(Variance::class);
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }
}
