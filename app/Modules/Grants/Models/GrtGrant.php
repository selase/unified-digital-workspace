<?php

declare(strict_types=1);

namespace App\Modules\Grants\Models;

use App\Modules\Finance\Models\FinFund;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GrtGrant extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['pipeline' => 'In pipeline', 'awarded' => 'Awarded', 'active' => 'Active', 'closed' => 'Closed', 'declined' => 'Declined'];

    protected $table = 'grt_grants';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** Money counts only once a grant is awarded or running. */
    public function isCommitted(): bool
    {
        return in_array($this->status, ['awarded', 'active', 'closed'], true);
    }

    /** @return BelongsTo<GrtFunder, $this> */
    public function funder(): BelongsTo
    {
        return $this->belongsTo(GrtFunder::class, 'funder_id');
    }

    /** @return BelongsTo<FinFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(FinFund::class, 'fund_id');
    }

    /** @return HasMany<GrtInstalment, $this> */
    public function instalments(): HasMany
    {
        return $this->hasMany(GrtInstalment::class, 'grant_id')->orderBy('expected_on');
    }

    /** @return HasMany<GrtReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(GrtReport::class, 'grant_id')->orderBy('due_on');
    }

    protected function casts(): array
    {
        return ['total_amount' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date'];
    }
}
