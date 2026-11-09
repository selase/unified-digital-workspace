<?php

declare(strict_types=1);

namespace App\Modules\Grants\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report the funder expects by a date. */
final class GrtReport extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'grt_reports';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_on->lt(today());
    }

    /** @return BelongsTo<GrtGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(GrtGrant::class, 'grant_id');
    }

    protected function casts(): array
    {
        return ['due_on' => 'date', 'submitted_on' => 'date'];
    }
}
