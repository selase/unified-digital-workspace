<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Models\Tenant;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Finance\Services\NumberSequence;
use App\Modules\Finance\Support\Money;
use App\Services\Alerts\AlertDispatcher;
use App\Services\Alerts\AlertEvent;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Records gifts against the Finance ledger: debit the account the money went into, credit the
 * donations income account, tagged with the fund. Gifts to restricted funds credit a separate
 * income account so restricted income can be reported on its own.
 */
final class DonationService
{
    public const UNRESTRICTED_INCOME = '4200';

    public const RESTRICTED_INCOME = '4210';

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly NumberSequence $numbers,
        private readonly FinanceSettings $settings,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /**
     * @param  array{method?: string, reference?: string|null, notes?: string|null, campaign_id?: int|null, fund_id?: int|null, deposit_account_id?: int|null}  $details
     */
    public function record(string $tenantId, DonDonor $donor, int $amount, CarbonInterface $date, array $details = [], ?string $userId = null): DonDonation
    {
        if ($amount <= 0) {
            throw new DomainException('The donation amount must be greater than zero.');
        }

        $campaign = isset($details['campaign_id'])
            ? DonCampaign::query()->where('tenant_id', $tenantId)->findOrFail($details['campaign_id'])
            : null;
        $fundId = $details['fund_id'] ?? $campaign?->fund_id;
        $fund = $fundId !== null ? FinFund::query()->where('tenant_id', $tenantId)->findOrFail($fundId) : null;

        $donation = DB::connection('tenant')->transaction(function () use ($tenantId, $donor, $amount, $date, $details, $userId, $campaign, $fund): DonDonation {
            $settings = $this->settings->for($tenantId);
            $depositId = $details['deposit_account_id'] ?? $this->accounts->bySystemKey($tenantId, ChartOfAccounts::BANK)->id;
            $income = $this->incomeAccount($tenantId, $fund);

            $donation = DonDonation::query()->create([
                'tenant_id' => $tenantId,
                'number' => $this->numbers->next($tenantId, 'donation', 'DON-'),
                'receipt_number' => $this->numbers->next($tenantId, 'donation_receipt', 'RCT-'),
                'donor_id' => $donor->id,
                'campaign_id' => $campaign?->id,
                'fund_id' => $fund?->id,
                'date' => $date->toDateString(),
                'amount' => $amount,
                'currency' => $settings->base_currency,
                'method' => $details['method'] ?? 'bank_transfer',
                'reference' => $details['reference'] ?? null,
                'deposit_account_id' => $depositId,
                'notes' => $details['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $journal = $this->ledger->post(
                $tenantId,
                $date,
                [
                    ['account_id' => $depositId, 'debit' => $amount, 'memo' => $donation->number, 'fund_id' => $fund?->id],
                    ['account_id' => $income->id, 'credit' => $amount, 'memo' => 'Donation from '.$donor->name, 'fund_id' => $fund?->id],
                ],
                'Donation '.$donation->number.' from '.$donor->name,
                'donation',
                $donation->id,
                $userId,
            );
            $donation->forceFill(['journal_id' => $journal->id])->save();

            return $donation;
        });

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant instanceof Tenant) {
            app(AlertDispatcher::class)->dispatchSafely(
                $tenant,
                AlertEvent::DONATION_RECEIVED,
                'Donation received',
                $donor->name.' gave '.Money::format($donation->amount, $donation->currency).' ('.$donation->receipt_number.').',
                'donation-'.$donation->id,
                route('donations.donations.index', [], false),
            );
        }

        return $donation;
    }

    public function void(DonDonation $donation, ?string $userId = null): void
    {
        if ($donation->isVoid()) {
            throw new DomainException('This donation is already void.');
        }

        DB::connection('tenant')->transaction(function () use ($donation, $userId): void {
            $journal = FinJournal::query()->find($donation->journal_id);
            if ($journal instanceof FinJournal) {
                $this->ledger->reverse($journal, null, $userId);
            }

            $donation->forceFill(['voided_at' => now()])->save();
        });
    }

    /** The income account for a gift: restricted gifts and unrestricted gifts are kept apart. */
    public function incomeAccount(string $tenantId, ?FinFund $fund): FinAccount
    {
        $this->accounts->ensureSeeded($tenantId);
        $restricted = $fund instanceof FinFund && $fund->is_restricted;

        return FinAccount::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => $restricted ? self::RESTRICTED_INCOME : self::UNRESTRICTED_INCOME],
            ['name' => $restricted ? 'Donations: restricted' : 'Donations: unrestricted', 'type' => 'income'],
        );
    }
}
