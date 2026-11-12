<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResultVarianceNote extends Model
{
    use BelongsToTenant;

    protected $table = 'rf_variance_notes';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open'];

    /** @return BelongsTo<ResultIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(ResultIndicator::class, 'indicator_id');
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
