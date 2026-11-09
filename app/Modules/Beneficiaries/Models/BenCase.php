<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BenCase extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CATEGORIES = ['general' => 'General', 'health' => 'Health', 'protection' => 'Protection', 'education' => 'Education', 'livelihood' => 'Livelihood', 'housing' => 'Housing'];

    protected $table = 'ben_cases';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<BenBeneficiary, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(BenBeneficiary::class, 'beneficiary_id');
    }

    /** @return HasMany<BenCaseNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(BenCaseNote::class, 'case_id')->latest('id');
    }

    protected function casts(): array
    {
        return ['opened_on' => 'date', 'closed_on' => 'date'];
    }
}
