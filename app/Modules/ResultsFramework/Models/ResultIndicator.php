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

    /** Where the current figure comes from. Manual indicators use the latest reading; the others are worked out from other modules. */
    public const SOURCES = [
        'manual' => 'Enter readings by hand',
        'beneficiaries_reached' => 'People reached (Beneficiaries services)',
        'volunteer_hours' => 'Volunteer hours (approved)',
        'donations_total' => 'Donations raised',
    ];

    public const STATUSES = ['active' => 'Active', 'archived' => 'Archived'];

    protected $table = 'rf_indicators';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'active', 'source' => 'manual', 'baseline' => 0, 'unit' => 'people'];

    /** @return HasMany<ResultReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(ResultReading::class, 'indicator_id')->orderByDesc('recorded_on')->orderByDesc('id');
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
        return ['baseline' => 'float', 'target' => 'float', 'starts_on' => 'date', 'ends_on' => 'date'];
    }
}
