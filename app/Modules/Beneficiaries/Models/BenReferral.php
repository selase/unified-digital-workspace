<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A person sent on to another service (clinic, school, social welfare), with a follow-up date so it is not forgotten. */
final class BenReferral extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['made' => 'Made', 'accepted' => 'Accepted', 'completed' => 'Completed', 'declined' => 'Declined'];

    protected $table = 'ben_referrals';

    /** @var list<string> */
    protected $guarded = ['id'];

    public function isOpen(): bool
    {
        return in_array($this->status, ['made', 'accepted'], true);
    }

    public function isOverdueForFollowUp(): bool
    {
        return $this->isOpen() && $this->follow_up_on !== null && $this->follow_up_on->lt(today());
    }

    /** @return BelongsTo<BenBeneficiary, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(BenBeneficiary::class, 'beneficiary_id');
    }

    protected function casts(): array
    {
        return ['referred_on' => 'date', 'follow_up_on' => 'date'];
    }
}
