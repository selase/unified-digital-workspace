<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VolHour extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'vol_hours';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function hoursDecimal(): float
    {
        return round($this->minutes / 60, 2);
    }

    /** @return BelongsTo<VolVolunteer, $this> */
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(VolVolunteer::class, 'volunteer_id')->withTrashed();
    }

    /** @return BelongsTo<VolOpportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolOpportunity::class, 'opportunity_id');
    }

    protected function casts(): array
    {
        return ['worked_on' => 'date', 'minutes' => 'integer', 'reviewed_at' => 'datetime'];
    }
}
