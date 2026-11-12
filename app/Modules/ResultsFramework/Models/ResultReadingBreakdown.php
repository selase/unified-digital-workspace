<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResultReadingBreakdown extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'rf_reading_breakdowns';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<ResultReading, $this> */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(ResultReading::class, 'reading_id');
    }

    protected function casts(): array
    {
        return ['value' => 'float'];
    }
}
