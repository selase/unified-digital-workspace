<?php

declare(strict_types=1);

namespace App\Modules\Grants\Models;

use App\Modules\Finance\Models\FinAccount;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of a grant is planned for one expense account. Spending on that account in the grant's fund is compared against it. */
final class GrtBudgetLine extends Model
{
    use BelongsToTenant;

    protected $table = 'grt_budget_lines';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<GrtGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(GrtGrant::class, 'grant_id');
    }

    /** @return BelongsTo<FinAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'account_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
