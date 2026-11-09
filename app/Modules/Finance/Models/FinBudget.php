<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A plan for a period: an amount per income or expense account (optionally per fund) for the whole period. */
final class FinBudget extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_budgets';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return HasMany<FinBudgetLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FinBudgetLine::class, 'budget_id');
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
