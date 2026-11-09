<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Models\DonPledge;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PledgeService
{
    public function __construct(private readonly DonationService $donations) {}

    /**
     * @param  array{frequency: string, starts_on: string, ends_on?: string|null, campaign_id?: int|null, fund_id?: int|null, notes?: string|null}  $details
     */
    public function create(string $tenantId, DonDonor $donor, int $amount, array $details): DonPledge
    {
        if ($amount <= 0) {
            throw new DomainException('The pledge amount must be greater than zero.');
        }
        if (! array_key_exists($details['frequency'], DonPledge::FREQUENCIES)) {
            throw new DomainException('Choose how often the donor will give.');
        }

        return DonPledge::query()->create([
            'tenant_id' => $tenantId,
            'donor_id' => $donor->id,
            'campaign_id' => $details['campaign_id'] ?? null,
            'fund_id' => $details['fund_id'] ?? null,
            'amount' => $amount,
            'frequency' => $details['frequency'],
            'starts_on' => $details['starts_on'],
            'next_due_on' => $details['starts_on'],
            'ends_on' => $details['ends_on'] ?? null,
            'status' => DonPledge::STATUS_ACTIVE,
            'notes' => $details['notes'] ?? null,
        ]);
    }

    /**
     * Books the instalment as a donation linked to the pledge, then moves the next due date forward.
     *
     * @param  array{method?: string, reference?: string|null, amount?: int|null}  $details
     */
    public function receiveInstalment(DonPledge $pledge, CarbonInterface $date, array $details = [], ?string $userId = null): DonDonation
    {
        if ($pledge->status !== DonPledge::STATUS_ACTIVE) {
            throw new DomainException('Only an active pledge can receive an instalment.');
        }

        return DB::connection('tenant')->transaction(function () use ($pledge, $date, $details, $userId): DonDonation {
            $gift = $this->donations->record(
                $pledge->tenant_id,
                $pledge->donor,
                $details['amount'] ?? $pledge->amount,
                $date,
                array_filter([
                    'method' => $details['method'] ?? 'bank_transfer',
                    'reference' => $details['reference'] ?? null,
                    'campaign_id' => $pledge->campaign_id,
                    'fund_id' => $pledge->fund_id,
                    'pledge_id' => $pledge->id,
                ], fn ($value): bool => $value !== null),
                $userId,
            );

            $next = match ($pledge->frequency) {
                'monthly' => $pledge->next_due_on->copy()->addMonthNoOverflow(),
                'quarterly' => $pledge->next_due_on->copy()->addMonthsNoOverflow(3),
                'yearly' => $pledge->next_due_on->copy()->addYearNoOverflow(),
                default => null,
            };

            if ($next === null || ($pledge->ends_on !== null && $next->gt($pledge->ends_on))) {
                $pledge->forceFill(['status' => DonPledge::STATUS_COMPLETED, 'next_due_on' => null])->save();
            } else {
                $pledge->forceFill(['next_due_on' => $next])->save();
            }

            return $gift;
        });
    }

    public function setStatus(DonPledge $pledge, string $status): DonPledge
    {
        if (! in_array($status, [DonPledge::STATUS_ACTIVE, DonPledge::STATUS_PAUSED, DonPledge::STATUS_CANCELLED], true)) {
            throw new DomainException('That is not a status you can set.');
        }
        if ($pledge->status === DonPledge::STATUS_COMPLETED || $pledge->status === DonPledge::STATUS_CANCELLED) {
            throw new DomainException('A completed or cancelled pledge cannot be changed.');
        }

        $pledge->forceFill(['status' => $status])->save();

        return $pledge;
    }
}
