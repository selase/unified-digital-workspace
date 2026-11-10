<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class VolVolunteer extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const STATUSES = ['pending' => 'Awaiting approval', 'active' => 'Active', 'inactive' => 'Inactive'];

    protected $table = 'vol_volunteers';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsToMany<VolOpportunity, $this> */
    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(VolOpportunity::class, 'vol_signups', 'volunteer_id', 'opportunity_id');
    }

    /** @return HasMany<VolHour, $this> */
    public function hours(): HasMany
    {
        return $this->hasMany(VolHour::class, 'volunteer_id');
    }

    protected function casts(): array
    {
        return ['joined_on' => 'date'];
    }
}
