<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ResultIndicator extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['active' => 'Active', 'archived' => 'Archived'];

    protected $table = 'rf_indicators';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'baseline' => 0, 'unit' => 'people'];

    /** @return HasMany<ResultReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(ResultReading::class, 'indicator_id')->orderByDesc('recorded_on')->orderByDesc('id');
    }

    /** @return HasMany<ResultTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(ResultTarget::class, 'indicator_id');
    }

    /** @return HasMany<ResultVarianceNote, $this> */
    public function varianceNotes(): HasMany
    {
        return $this->hasMany(ResultVarianceNote::class, 'indicator_id')->latest()->orderByDesc('id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(ResultProgramme::class, 'programme_id');
    }

    public function programmeName(): string
    {
        return $this->programme?->name ?? 'General';
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ResultNode::class, 'result_id');
    }

    protected function casts(): array
    {
        return ['baseline' => 'float', 'target' => 'float', 'starts_on' => 'date', 'ends_on' => 'date', 'measure_filters' => 'array'];
    }
}
