<?php

declare(strict_types=1);

namespace App\Modules\Grants\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GrtInstalment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'grt_instalments';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function isOverdue(): bool
    {
        return $this->status === 'expected' && $this->expected_on->lt(today());
    }

    /** @return BelongsTo<GrtGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(GrtGrant::class, 'grant_id');
    }

    protected function casts(): array
    {
        return ['expected_on' => 'date', 'received_on' => 'date', 'amount' => 'integer'];
    }
}
