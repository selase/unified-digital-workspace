<?php

declare(strict_types=1);

namespace App\Modules\ProgrammeResults\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResultReading extends Model
{
    use BelongsToTenant;

    protected $table = 'pr_readings';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<ResultIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(ResultIndicator::class, 'indicator_id');
    }

    protected function casts(): array
    {
        return ['value' => 'float', 'recorded_on' => 'date'];
    }
}
