<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Objective extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'qm_objectives';

    protected $fillable = [
        'tenant_id',
        'workplan_id',
        'title',
        'description',
        'weight',
        'status',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Workplan, $this>
     */
    public function workplan(): BelongsTo
    {
        return $this->belongsTo(Workplan::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
