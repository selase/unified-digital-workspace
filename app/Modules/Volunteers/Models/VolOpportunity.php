<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A task, event or shift that needs volunteers. A spots value of 0 means unlimited. */
final class VolOpportunity extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'vol_opportunities';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function isFull(): bool
    {
        return $this->spots > 0 && $this->volunteers()->count() >= $this->spots;
    }

    /** @return BelongsToMany<VolVolunteer, $this> */
    public function volunteers(): BelongsToMany
    {
        return $this->belongsToMany(VolVolunteer::class, 'vol_signups', 'opportunity_id', 'volunteer_id')->withTimestamps();
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'spots' => 'integer'];
    }
}
