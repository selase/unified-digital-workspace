<?php

declare(strict_types=1);

namespace App\Modules\Donations\Models;

use App\Modules\Finance\Models\FinFund;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A donor's promise to give, once or on a schedule. The money is only booked when an instalment is received. */
final class DonPledge extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const FREQUENCIES = ['once' => 'One time', 'monthly' => 'Monthly', 'quarterly' => 'Every 3 months', 'yearly' => 'Yearly'];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'don_pledges';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** True when the donor allowed automatic charges and a reusable card is on file. */
    public function canAutoCharge(): bool
    {
        return $this->auto_charge && $this->authorization_code !== null && $this->status === self::STATUS_ACTIVE;
    }

    public function isDue(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->next_due_on !== null && $this->next_due_on->lte(today());
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->next_due_on !== null && $this->next_due_on->lt(today());
    }

    /** @return BelongsTo<DonDonor, $this> */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(DonDonor::class, 'donor_id')->withTrashed();
    }

    /** @return BelongsTo<DonCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<FinFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(FinFund::class, 'fund_id');
    }

    /** @return HasMany<DonDonation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(DonDonation::class, 'pledge_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'starts_on' => 'date', 'next_due_on' => 'date', 'ends_on' => 'date', 'auto_charge' => 'boolean', 'authorization_code' => \App\Casts\EncryptedString::class, 'consent_at' => 'datetime', 'last_charge_attempt_on' => 'date'];
    }
}
