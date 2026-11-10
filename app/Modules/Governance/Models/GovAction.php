<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something a person agreed to do, with a date, so it is not forgotten between meetings. */
final class GovAction extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'gov_actions';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'open'];

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_on !== null && $this->due_on->lt(today());
    }

    /** @return BelongsTo<GovMeeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovMeeting::class, 'meeting_id');
    }

    /** @return BelongsTo<GovMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(GovMember::class, 'member_id');
    }

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_on' => 'date'];
    }
}
