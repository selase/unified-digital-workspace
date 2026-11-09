<?php

declare(strict_types=1);

namespace App\Modules\Donations\Models;

use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A gift received. Voiding keeps the row and reverses its ledger entry. */
final class DonDonation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'don_donations';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
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

    /** @return BelongsTo<FinAccount, $this> */
    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(FinAccount::class, 'deposit_account_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'integer', 'receipt_sent_at' => 'datetime', 'voided_at' => 'datetime'];
    }
}
