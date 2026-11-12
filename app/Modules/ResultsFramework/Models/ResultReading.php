<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ResultReading extends Model
{
    use BelongsToTenant;

    protected $table = 'rf_readings';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<ResultIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(ResultIndicator::class, 'indicator_id');
    }

    /** @return HasMany<ResultReadingBreakdown, $this> */
    public function breakdowns(): HasMany
    {
        return $this->hasMany(ResultReadingBreakdown::class, 'reading_id');
    }

    protected function casts(): array
    {
        return ['value' => 'float', 'recorded_on' => 'date'];
    }
}
