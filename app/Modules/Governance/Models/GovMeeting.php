<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GovMeeting extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const BODIES = ['Board', 'Executive committee', 'Finance committee', 'Programme committee', 'AGM'];

    public const STATUSES = ['scheduled' => 'Scheduled', 'held' => 'Held', 'cancelled' => 'Cancelled'];

    protected $table = 'gov_meetings';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'scheduled', 'body' => 'Board', 'minutes_status' => 'none'];

    /** @return BelongsToMany<GovMember, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(GovMember::class, 'gov_attendance', 'meeting_id', 'member_id')->withPivot('status')->withTimestamps();
    }

    /** @return HasMany<GovResolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(GovResolution::class, 'meeting_id')->orderBy('reference');
    }

    /** @return HasMany<GovAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(GovAction::class, 'meeting_id')->orderBy('due_on');
    }

    protected function casts(): array
    {
        return ['meets_at' => 'datetime', 'minutes_approved_on' => 'date', 'quorum' => 'integer'];
    }
}
