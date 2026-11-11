<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Services\DonationService;
use App\Modules\Donations\Services\PledgeService;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/** Donors, campaigns, gifts and pledges without any saved-card consent, plus the spending that mirrors them. */
final class DonationsSection implements DemoSection
{
    private const DONORS = [
        ['Demo: Ama Boateng', 'individual', 'ama.boateng@example.test'],
        ['Demo: Kwame Owusu', 'individual', 'kwame.owusu@example.test'],
        ['Demo: Ghana Endocrine Society', 'organisation', 'secretary@ghanaendocrine.test'],
        ['Demo: Gold Coast Bank Foundation', 'organisation', 'csr@goldcoastbank.test'],
        ['Demo: Efua Mensah', 'individual', 'efua.mensah@example.test'],
        ['Demo: Dr. Yaw Adjei', 'individual', 'yaw.adjei@example.test'],
        ['Demo: Accra Rotary Club', 'organisation', 'chair@accrarotary.test'],
        ['Demo: Nana Akosua Frimpong', 'individual', 'nana.frimpong@example.test'],
    ];

    public function label(): string
    {
        return 'Donations';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $accounts = app(ChartOfAccounts::class);
        $accounts->addNgoAccounts($tenantId);

        $general = FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'DGEN'], ['name' => 'Demo: General fund', 'is_restricted' => false]);
        $screening = FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'DSCR'], ['name' => 'Demo: Screening camps (restricted)', 'is_restricted' => true]);
        $surgery = FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'DSRG'], ['name' => 'Demo: Subsidised surgery (restricted)', 'is_restricted' => true]);

        $campaigns = [
            'general' => DonCampaign::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Annual giving 2026', 'description' => 'Unrestricted support for our work.', 'goal_amount' => 5_000_000, 'fund_id' => $general->id, 'starts_on' => today()->startOfYear()]),
            'surgery' => DonCampaign::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Subsidised thyroid surgery', 'description' => 'Helping patients who cannot afford the operation they need.', 'goal_amount' => 8_000_000, 'fund_id' => $surgery->id, 'starts_on' => today()->subMonths(6)]),
            'screening' => DonCampaign::query()->create(['tenant_id' => $tenantId, 'name' => 'Demo: Free screening camps', 'description' => 'Community thyroid screening and education days.', 'goal_amount' => 4_000_000, 'fund_id' => $screening->id, 'starts_on' => today()->subMonths(3)]),
        ];

        $donors = [];
        foreach (self::DONORS as [$name, $type, $email]) {
            $donors[] = DonDonor::query()->create(['tenant_id' => $tenantId, 'name' => $name, 'type' => $type, 'email' => $email, 'phone' => '+23324'.random_int(1000000, 9999999)]);
        }

        $service = app(DonationService::class);
        $methods = ['bank_transfer', 'mobile_money', 'cash', 'card'];
        $gifts = [
            [0, 7, 5, 50_000, 'general'], [0, 4, 5, 50_000, 'surgery'], [0, 1, 5, 75_000, 'screening'],
            [1, 6, 12, 120_000, 'surgery'], [1, 2, 12, 120_000, 'surgery'],
            [2, 5, 20, 1_500_000, 'surgery'], [2, 1, 18, 1_000_000, 'screening'],
            [3, 4, 3, 2_500_000, 'screening'], [3, 0, 2, 1_000_000, 'general'],
            [4, 3, 9, 30_000, 'general'], [4, 2, 9, 30_000, 'general'], [4, 1, 9, 30_000, 'general'], [4, 0, 9, 30_000, 'general'],
            [5, 5, 15, 400_000, 'screening'], [5, 0, 15, 600_000, 'screening'],
            [6, 2, 22, 350_000, 'surgery'],
            [7, 1, 27, 200_000, 'general'], [7, 0, 4, 150_000, 'surgery'],
        ];

        $recorded = [];
        foreach ($gifts as $index => [$donorIndex, $monthsAgo, $day, $amount, $campaignKey]) {
            $date = today()->subMonthsNoOverflow($monthsAgo)->day(min($day, 28));
            if ($date->isFuture()) {
                $date = today();
            }
            $recorded[] = $service->record($tenantId, $donors[$donorIndex], $amount, $date, [
                'method' => $methods[$index % count($methods)],
                'campaign_id' => $campaigns[$campaignKey]->id,
                'reference' => 'DEMO-'.mb_str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ], null);
        }

        $pledges = app(PledgeService::class);
        $pledges->create($tenantId, $donors[0], 50_000, ['frequency' => 'monthly', 'starts_on' => today()->subDays(5)->toDateString(), 'campaign_id' => $campaigns['surgery']->id]);
        $pledges->create($tenantId, $donors[3], 1_000_000, ['frequency' => 'quarterly', 'starts_on' => today()->addDays(40)->toDateString(), 'campaign_id' => $campaigns['screening']->id]);
        $pledges->create($tenantId, $donors[4], 30_000, ['frequency' => 'monthly', 'starts_on' => today()->toDateString(), 'fund_id' => $general->id]);
        $pledges->create($tenantId, $donors[2], 500_000, ['frequency' => 'once', 'starts_on' => today()->addDays(14)->toDateString(), 'campaign_id' => $campaigns['surgery']->id]);

        $expenses = app(ExpenseService::class);
        $bank = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $account = fn (string $code): int => FinAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail()->id;
        foreach ([
            [5, '5100', 'Thyroid function test kits for screening camps', $screening->id, 650_000],
            [3, '5100', 'Camp logistics and volunteer meals', $screening->id, 420_000],
            [2, '5100', 'Surgery subsidy paid to the hospital for two patients', $surgery->id, 1_800_000],
            [1, '5100', 'Patient transport to surgery appointments', $surgery->id, 350_000],
            [4, '5200', 'Office rent', $general->id, 300_000],
            [1, '5200', 'Accounting and audit fees', $general->id, 250_000],
            [2, '5300', 'Fundraising event printing', $general->id, 90_000],
        ] as [$monthsAgo, $code, $description, $fundId, $amount]) {
            $expenses->record($tenantId, $amount, today()->subMonthsNoOverflow($monthsAgo)->day(14), $account($code), $bank->id, null, ['description' => $description, 'fund_id' => $fundId], null);
        }

        $service->void($recorded[3], null);
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        return $remover->children('don_pledges', 'donor_id', 'don_donors', $tenant, $since, 'name')
            + $remover->children('don_donations', 'donor_id', 'don_donors', $tenant, $since, 'name')
            + $remover->rows('don_campaigns', $tenant, $since, 'name') + $remover->rows('don_donors', $tenant, $since, 'name');
    }
}
