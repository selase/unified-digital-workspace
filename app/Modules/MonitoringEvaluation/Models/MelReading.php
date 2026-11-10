<?php

declare(strict_types=1);

namespace App\Modules\MonitoringEvaluation\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MelReading extends Model
{
    use BelongsToTenant;

    protected $table = 'mel_readings';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<MelIndicator, $this> */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(MelIndicator::class, 'indicator_id');
    }

    protected function casts(): array
    {
        return ['value' => 'float', 'recorded_on' => 'date'];
    }
}
