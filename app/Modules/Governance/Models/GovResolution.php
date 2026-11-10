<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GovResolution extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const OUTCOMES = ['passed' => 'Passed', 'rejected' => 'Rejected', 'deferred' => 'Deferred'];

    protected $table = 'gov_resolutions';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<GovMeeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovMeeting::class, 'meeting_id');
    }
}
