<?php

declare(strict_types=1);

namespace App\Modules\Donations\Models;

use App\Modules\Finance\Models\FinFund;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DonCampaign extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'don_campaigns';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return BelongsTo<FinFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(FinFund::class, 'fund_id');
    }

    /** @return HasMany<DonDonation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(DonDonation::class, 'campaign_id');
    }

    protected function casts(): array
    {
        return ['goal_amount' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }
}
