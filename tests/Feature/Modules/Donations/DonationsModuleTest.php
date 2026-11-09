<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Donations\Database\Seeders\DonationsDemoSeeder;
use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use App\Modules\Donations\Models\DonDonor;
use App\Modules\Donations\Services\DonationReports;
use App\Modules\Donations\Services\DonationService;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Models\FinJournal;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Finance\Services\StatementReports;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createDonationsContext(bool $withPermissions = true): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [...config('modules.finance.permissions'), ...config('modules.donations.permissions')];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'donations', 'uuid' => (string) Str::uuid()]);
    }
    if ($withPermissions) {
        $user->givePermissionTo($permissions);
    }

    app(TenantDatabaseManager::class)->configure($tenant);
    foreach (['finance', 'donations'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }

    foreach (['Finance', 'Donations'] as $module) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$module}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }

    return [$user, $tenant];
}

function demoDonor(Tenant $tenant, string $name = 'Ama Boateng', ?string $email = 'ama@example.test'): DonDonor
{
    return DonDonor::query()->create(['tenant_id' => $tenant->id, 'name' => $name, 'email' => $email]);
}

function ledgerBalance(Tenant $tenant, string $code): int
{
    $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();
    if ($account === null) {
        return 0;
    }

    $row = DB::connection('tenant')->table('fin_journal_lines')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

    return (int) $row->d - (int) $row->c;
}

describe('Donations ledger', function (): void {
    test('a gift debits the bank and credits unrestricted income, with numbered receipts', function (): void {
        [, $tenant] = createDonationsContext();
        $donor = demoDonor($tenant);

        $gift = app(DonationService::class)->record($tenant->id, $donor, 50_000, today());

        expect($gift->number)->toBe('DON-00001')->and($gift->receipt_number)->toBe('RCT-00001')
            ->and(ledgerBalance($tenant, '1000'))->toBe(50_000)
            ->and(ledgerBalance($tenant, '4200'))->toBe(-50_000);

        $second = app(DonationService::class)->record($tenant->id, $donor, 10_000, today());
        expect($second->receipt_number)->toBe('RCT-00002');
    });

    test('a gift to a restricted fund goes to restricted income and shows in the fund report', function (): void {
        [, $tenant] = createDonationsContext();
        $fund = FinFund::query()->create(['tenant_id' => $tenant->id, 'code' => 'SCH', 'name' => 'School feeding', 'is_restricted' => true]);
        $campaign = DonCampaign::query()->create(['tenant_id' => $tenant->id, 'name' => 'Feed a child', 'goal_amount' => 100_000, 'fund_id' => $fund->id]);

        $gift = app(DonationService::class)->record($tenant->id, demoDonor($tenant), 75_000, today(), ['campaign_id' => $campaign->id]);

        expect($gift->fund_id)->toBe($fund->id)->and(ledgerBalance($tenant, '4210'))->toBe(-75_000)->and(ledgerBalance($tenant, '4200'))->toBe(0);

        $report = app(StatementReports::class)->fundReport($tenant->id, today()->startOfYear(), today());
        $row = collect($report['rows'])->firstWhere('name', 'School feeding');
        expect($row['income'])->toBe(75_000)->and($row['balance'])->toBe(75_000);

        $progress = app(DonationReports::class)->campaigns($tenant->id)->first();
        expect($progress['raised'])->toBe(75_000)->and($progress['percent'])->toBe(75);
    });

    test('rejects a zero gift and voiding reverses the entry once', function (): void {
        [, $tenant] = createDonationsContext();
        $service = app(DonationService::class);

        expect(fn () => $service->record($tenant->id, demoDonor($tenant), 0, today()))->toThrow(DomainException::class);

        $gift = $service->record($tenant->id, demoDonor($tenant, 'B'), 20_000, today());
        $service->void($gift);

        expect($gift->fresh()->isVoid())->toBeTrue()->and(ledgerBalance($tenant, '1000'))->toBe(0)->and(ledgerBalance($tenant, '4200'))->toBe(0)
            ->and(fn () => $service->void($gift->fresh()))->toThrow(DomainException::class)
            ->and(app(DonationReports::class)->summary($tenant->id, today()->startOfYear(), today())['total'])->toBe(0);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);
    });

    test('summary and top donors total only live gifts', function (): void {
        [, $tenant] = createDonationsContext();
        $service = app(DonationService::class);
        $big = demoDonor($tenant, 'Big Giver');
        $small = demoDonor($tenant, 'Small Giver');
        $service->record($tenant->id, $big, 900_000, today());
        $service->record($tenant->id, $big, 100_000, today());
        $service->record($tenant->id, $small, 50_000, today(), ['method' => 'cash']);
        $service->void($service->record($tenant->id, $small, 5_000_000, today()));

        $reports = app(DonationReports::class);
        $summary = $reports->summary($tenant->id, today()->startOfYear(), today());
        expect($summary['total'])->toBe(1_050_000)->and($summary['count'])->toBe(3)->and($summary['donors'])->toBe(2)->and($summary['average'])->toBe(350_000);

        $top = $reports->topDonors($tenant->id, today()->startOfYear(), today());
        expect($top->first()->name)->toBe('Big Giver')->and($top->first()->total)->toBe(1_000_000);
    });

    test('the foundation chart template is added once and the finance statements use it', function (): void {
        [, $tenant] = createDonationsContext();
        $chart = app(ChartOfAccounts::class);

        expect($chart->addNgoAccounts($tenant->id))->toBe(8)->and($chart->addNgoAccounts($tenant->id))->toBe(0)
            ->and(FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '5100')->value('name'))->toBe('Programme expenses');
    });
});

describe('Donations screens', function (): void {
    test('the screens add a donor and a campaign, record a gift and open the receipt', function (): void {
        [$user, $tenant] = createDonationsContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $fund = FinFund::query()->create(['tenant_id' => $tenant->id, 'code' => 'GEN', 'name' => 'General fund']);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('donations.index'))->assertOk()->assertSee('Raised this year');
        $this->post(route('donations.donors.store'), ['name' => 'Kofi Annan', 'type' => 'individual', 'email' => 'kofi@example.test'])->assertRedirect();
        $donor = DonDonor::query()->firstOrFail();
        $this->get(route('donations.donors.show', $donor->uuid))->assertOk()->assertSee('Kofi Annan');
        $this->post(route('donations.campaigns.store'), ['name' => 'Appeal', 'goal_amount' => '1000', 'fund_id' => $fund->id])->assertRedirect();
        $campaign = DonCampaign::query()->firstOrFail();
        expect($campaign->goal_amount)->toBe(100_000);

        $this->post(route('donations.donations.store'), [
            'donor_id' => $donor->id, 'date' => today()->toDateString(), 'amount' => '250.00', 'method' => 'cash', 'campaign_id' => $campaign->id,
        ])->assertRedirect(route('donations.donations.index'));
        $gift = DonDonation::query()->firstOrFail();
        expect($gift->amount)->toBe(25_000)->and($gift->fund_id)->toBe($fund->id);

        $this->get(route('donations.donations.index'))->assertOk()->assertSee('RCT-00001');
        $this->get(route('donations.campaigns.index'))->assertOk()->assertSee('Appeal');
        $this->get(route('donations.donors.index', ['search' => 'Kofi']))->assertOk()->assertSee('Kofi Annan');
        $receipt = $this->get(route('donations.donations.receipt', $gift->uuid))->assertOk();
        expect($receipt->headers->get('Content-Type'))->toContain('application/pdf')->and(mb_substr($receipt->getContent(), 0, 4))->toBe('%PDF');

        $this->post(route('donations.campaigns.toggle', $campaign->uuid))->assertRedirect();
        expect($campaign->fresh()->is_active)->toBeFalse();
    });

    test('a receipt can be emailed once the donor has an address, and a gift can be voided from the screen', function (): void {
        Mail::fake();
        [$user, $tenant] = createDonationsContext();
        $service = app(DonationService::class);
        $withEmail = $service->record($tenant->id, demoDonor($tenant), 10_000, today());
        $noEmail = $service->record($tenant->id, demoDonor($tenant, 'No Mail', null), 10_000, today());
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('donations.donations.email', $withEmail->uuid))->assertSessionHas('success');
        Mail::assertSent(App\Modules\Donations\Mail\DonationReceiptMail::class, fn ($mail): bool => $mail->hasTo('ama@example.test'));
        expect($withEmail->fresh()->receipt_sent_at)->not->toBeNull();

        $this->post(route('donations.donations.email', $noEmail->uuid))->assertSessionHas('error');

        $this->post(route('donations.donations.void', $withEmail->uuid))->assertSessionHas('success');
        expect($withEmail->fresh()->isVoid())->toBeTrue();
    });

    test('validation and permissions are enforced', function (): void {
        [$user, $tenant] = createDonationsContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('donations.donations.store'), ['amount' => '0'])->assertSessionHasErrors(['donor_id', 'amount']);
        $this->post(route('donations.donors.store'), [])->assertSessionHasErrors('name');

        $user->revokePermissionTo(['donations.record', 'donations.donors.manage', 'donations.campaigns.manage']);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('donations.index'))->assertOk();
        $this->post(route('donations.donations.store'), [])->assertForbidden();
        $this->post(route('donations.donors.store'), ['name' => 'X'])->assertForbidden();

        $user->revokePermissionTo('donations.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('donations.index'))->assertForbidden();
    });
});

describe('Donations demo seeder', function (): void {
    test('creates a year of giving that balances, once, and refuses production', function (): void {
        [, $tenant] = createDonationsContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(DonationsDemoSeeder::class);

        expect(DonDonor::query()->count())->toBe(8)->and(DonCampaign::query()->count())->toBe(3)
            ->and(DonDonation::query()->whereNotNull('voided_at')->count())->toBe(1)
            ->and(DonDonation::query()->whereNull('voided_at')->count())->toBe(17);
        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);

        $journals = FinJournal::query()->count();
        $this->seed(DonationsDemoSeeder::class);
        expect(FinJournal::query()->count())->toBe($journals);

        DonDonation::query()->delete();
        DonCampaign::query()->delete();
        DonDonor::query()->forceDelete();
        app()->detectEnvironment(fn (): string => 'production');
        (new DonationsDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(DonDonor::query()->count())->toBe(0);
    });
});
