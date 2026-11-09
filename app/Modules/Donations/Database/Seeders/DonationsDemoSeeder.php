<?php

declare(strict_types=1);

namespace App\Modules\Donations\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Services\DonationService;
use App\Modules\Donations\Services\PledgeService;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\FinanceSettings;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/**
 * A foundation's year of giving: donors, campaigns tied to funds, and gifts spread over the last months,
 * so the donations dashboard, receipts and fund report can be shown. Posts real ledger entries, so it
 * never runs in production and runs once per tenant. Pick the tenant with DEMO_TENANT.
 *
 *   DEMO_TENANT=demo-foundation php artisan db:seed --class="App\Modules\Donations\Database\Seeders\DonationsDemoSeeder"
 */
final class DonationsDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Ama Serwaa Boateng';

    /** @var list<array{0: string, 1: string, 2: string}> name, type, email */
    private const DONORS = [
        [self::MARKER, 'individual', 'ama.boateng@example.test'],
        ['Demo: Kwame Owusu', 'individual', 'kwame.owusu@example.test'],
        ['Demo: Accra Rotary Club', 'organisation', 'secretary@accrarotary.test'],
        ['Demo: Gold Coast Bank Foundation', 'organisation', 'csr@goldcoastbank.test'],
        ['Demo: Efua Mensah', 'individual', 'efua.mensah@example.test'],
        ['Demo: Dr. Yaw Adjei', 'individual', 'yaw.adjei@example.test'],
        ['Demo: Tema Fishermen Association', 'organisation', 'chair@temafishers.test'],
        ['Demo: Nana Akosua Frimpong', 'individual', 'nana.frimpong@example.test'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DonationsDemoSeeder: refusing to write demo gifts into production books.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('DonationsDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        $accounts = app(ChartOfAccounts::class);
        $accounts->addNgoAccounts($tenantId);

        if (DonDonor::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('DonationsDemoSeeder: demo donations already present, skipping.');

            return;
        }

        $userId = User::query()->where('tenant_id', $tenantId)->value('uuid');
        $userId = $userId ? (string) $userId : null;

        $general = FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'GEN'], ['name' => 'General fund', 'is_restricted' => false]);
        $school = FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'SCH'], ['name' => 'School feeding (restricted)', 'is_restricted' => true]);
        $clinic = FinFund::query()->firstOrCreate(['tenant_id' => $tenantId, 'code' => 'CLN'], ['name' => 'Mobile clinic (restricted)', 'is_restricted' => true]);

        $campaigns = [
            'general' => DonCampaign::query()->create(['tenant_id' => $tenantId, 'name' => 'Annual giving 2026', 'description' => 'Unrestricted support for our work.', 'goal_amount' => 5_000_000, 'fund_id' => $general->id, 'starts_on' => today()->startOfYear()]),
            'school' => DonCampaign::query()->create(['tenant_id' => $tenantId, 'name' => 'Feed a child, teach a child', 'description' => 'Daily meals for 300 pupils in the Volta region.', 'goal_amount' => 8_000_000, 'fund_id' => $school->id, 'starts_on' => today()->subMonths(6)]),
            'clinic' => DonCampaign::query()->create(['tenant_id' => $tenantId, 'name' => 'Mobile clinic for the north', 'description' => 'A vehicle, a nurse and medicines for villages without a clinic.', 'goal_amount' => 12_000_000, 'fund_id' => $clinic->id, 'starts_on' => today()->subMonths(3)]),
        ];

        $donors = [];
        foreach (self::DONORS as [$name, $type, $email]) {
            $donors[] = DonDonor::query()->create(['tenant_id' => $tenantId, 'name' => $name, 'type' => $type, 'email' => $email, 'phone' => '+23324'.random_int(1000000, 9999999)]);
        }

        $service = app(DonationService::class);
        $methods = ['bank_transfer', 'mobile_money', 'cash', 'card'];
        // [donor index, months ago, day of month, amount in pesewas, campaign key]
        $gifts = [
            [0, 7, 5, 50_000, 'general'], [0, 4, 5, 50_000, 'school'], [0, 1, 5, 75_000, 'clinic'],
            [1, 6, 12, 120_000, 'school'], [1, 2, 12, 120_000, 'school'],
            [2, 5, 20, 1_500_000, 'school'], [2, 1, 18, 1_000_000, 'clinic'],
            [3, 4, 3, 2_500_000, 'clinic'], [3, 0, 2, 1_000_000, 'general'],
            [4, 3, 9, 30_000, 'general'], [4, 2, 9, 30_000, 'general'], [4, 1, 9, 30_000, 'general'], [4, 0, 9, 30_000, 'general'],
            [5, 5, 15, 400_000, 'clinic'], [5, 0, 15, 600_000, 'clinic'],
            [6, 2, 22, 350_000, 'school'],
            [7, 1, 27, 200_000, 'general'], [7, 0, 4, 150_000, 'school'],
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
            ], $userId);
        }

        // Regular givers: one is due today and one is overdue, so the pledges screen has something to act on.
        $pledges = app(PledgeService::class);
        $pledges->create($tenantId, $donors[0], 50_000, ['frequency' => 'monthly', 'starts_on' => today()->subDays(5)->toDateString(), 'campaign_id' => $campaigns['school']->id]);
        $pledges->create($tenantId, $donors[3], 1_000_000, ['frequency' => 'quarterly', 'starts_on' => today()->addDays(40)->toDateString(), 'campaign_id' => $campaigns['clinic']->id]);
        $pledges->create($tenantId, $donors[4], 30_000, ['frequency' => 'monthly', 'starts_on' => today()->toDateString(), 'fund_id' => $general->id]);
        $pledges->create($tenantId, $donors[2], 500_000, ['frequency' => 'once', 'starts_on' => today()->addDays(14)->toDateString(), 'campaign_id' => $campaigns['school']->id]);

        // What the foundation spent, by programme, so the fund report and the profit and loss have both sides.
        $expenses = app(ExpenseService::class);
        $bank = $accounts->bySystemKey($tenantId, ChartOfAccounts::BANK);
        $account = fn (string $code): int => FinAccount::query()->where('tenant_id', $tenantId)->where('code', $code)->firstOrFail()->id;
        foreach ([
            [5, '5100', 'Rice, beans and cooking oil for school meals', $school->id, 650_000],
            [3, '5100', 'Cooks and kitchen supplies, school feeding', $school->id, 420_000],
            [2, '5100', 'Medicines and consumables for the mobile clinic', $clinic->id, 1_800_000],
            [1, '5100', 'Fuel and driver, mobile clinic', $clinic->id, 350_000],
            [4, '5200', 'Office rent', $general->id, 300_000],
            [1, '5200', 'Accounting and audit fees', $general->id, 250_000],
            [2, '5300', 'Fundraising event printing', $general->id, 90_000],
        ] as [$monthsAgo, $code, $description, $fundId, $amount]) {
            $expenses->record($tenantId, $amount, today()->subMonthsNoOverflow($monthsAgo)->day(14), $account($code), $bank->id, null, ['description' => $description, 'fund_id' => $fundId], $userId);
        }

        $profile = app(FinanceSettings::class)->for($tenantId);
        if (! $profile->legal_name) {
            $profile->update(['legal_name' => $tenant->name]);
        }

        // A mistaken entry that was cancelled, to show voiding.
        $service->void($recorded[3], $userId);

        $this->command?->info('DonationsDemoSeeder: '.count($donors).' donors, '.count($campaigns).' campaigns and '.count($recorded).' gifts created.');
    }
}
