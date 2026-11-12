<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResultTarget extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'rf_targets';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<ResultIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(ResultIndicator::class, 'indicator_id');
    }

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'target_value' => 'float'];
    }
}
