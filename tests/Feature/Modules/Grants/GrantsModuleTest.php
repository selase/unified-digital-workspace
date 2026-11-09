<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\Finance\Models\FinAccount;
use App\Modules\Finance\Models\FinFund;
use App\Modules\Finance\Services\ChartOfAccounts;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\FinanceReports;
use App\Modules\Grants\Database\Seeders\GrantsDemoSeeder;
use App\Modules\Grants\Models\GrtFunder;
use App\Modules\Grants\Models\GrtGrant;
use App\Modules\Grants\Models\GrtInstalment;
use App\Modules\Grants\Models\GrtReport;
use App\Modules\Grants\Services\GrantService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createGrantsContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [...config('modules.finance.permissions'), ...config('modules.grants.permissions')];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'grants', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    foreach (['finance', 'grants'] as $slug) {
        TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-'.$slug, 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
        app(ModuleManager::class)->enableForTenant($slug, $tenant);
    }
    foreach (['Finance', 'Grants'] as $module) {
        Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path("Modules/{$module}/Database/Migrations"), '--realpath' => true, '--force' => true]);
    }

    return [$user, $tenant];
}

function awardedGrant(Tenant $tenant, int $amount = 1_000_000, string $status = 'awarded'): GrtGrant
{
    $funder = GrtFunder::query()->create(['tenant_id' => $tenant->id, 'name' => 'Wellspring', 'type' => 'foundation']);

    return app(GrantService::class)->create($tenant->id, $funder, $amount, ['title' => 'Clean water', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => $status]);
}

function grantLedger(Tenant $tenant, string $code): int
{
    $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', $code)->first();
    if ($account === null) {
        return 0;
    }
    $row = DB::connection('tenant')->table('fin_journal_lines')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

    return (int) $row->d - (int) $row->c;
}

describe('Grants ledger', function (): void {
    test('a new grant gets its own restricted fund and rejects bad amounts and dates', function (): void {
        [, $tenant] = createGrantsContext();
        $grant = awardedGrant($tenant);

        expect($grant->fund->is_restricted)->toBeTrue()->and($grant->fund->code)->toBe('GR-001')->and($grant->fund->name)->toContain('Clean water');

        $funder = GrtFunder::query()->firstOrFail();
        $service = app(GrantService::class);
        expect(fn () => $service->create($tenant->id, $funder, 0, ['title' => 'x']))->toThrow(DomainException::class)
            ->and(fn () => $service->create($tenant->id, $funder, 100, ['title' => 'x', 'starts_on' => '2026-06-01', 'ends_on' => '2026-01-01']))->toThrow(DomainException::class);
        expect($service->create($tenant->id, $funder, 100, ['title' => 'Second'])->fund->code)->toBe('GR-002');
    });

    test('instalments cannot exceed the grant and receiving one posts to the bank and grant income by fund', function (): void {
        [, $tenant] = createGrantsContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $grant = awardedGrant($tenant, 1_000_000);
        $service = app(GrantService::class);

        $first = $service->addInstalment($grant, 600_000, today());
        expect(fn () => $service->addInstalment($grant, 500_000, today()))->toThrow(DomainException::class, 'more than the grant');
        $service->addInstalment($grant, 400_000, today()->addMonth());

        $service->receive($first, today(), null, 'TRF-1');
        expect($first->fresh()->status)->toBe('received')->and(grantLedger($tenant, '1000'))->toBe(600_000)->and(grantLedger($tenant, '4220'))->toBe(-600_000)
            ->and(fn () => $service->receive($first->fresh(), today()))->toThrow(DomainException::class);

        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);
    });

    test('money cannot be received against a grant that is only in the pipeline', function (): void {
        [, $tenant] = createGrantsContext();
        $grant = awardedGrant($tenant, 500_000, 'pipeline');
        $instalment = app(GrantService::class)->addInstalment($grant, 100_000, today());

        expect(fn () => app(GrantService::class)->receive($instalment, today()))->toThrow(DomainException::class, 'awarded');
    });

    test('voiding a received instalment reverses the entry once', function (): void {
        [, $tenant] = createGrantsContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(GrantService::class);
        $instalment = $service->receive($service->addInstalment(awardedGrant($tenant), 300_000, today()), today());

        $service->void($instalment);
        expect($instalment->fresh()->status)->toBe('void')->and(grantLedger($tenant, '1000'))->toBe(0)->and(grantLedger($tenant, '4220'))->toBe(0)
            ->and(fn () => $service->void($instalment->fresh()))->toThrow(DomainException::class);
    });

    test('the position counts only expenses tagged to the grant fund inside its dates', function (): void {
        [, $tenant] = createGrantsContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $grant = awardedGrant($tenant, 1_000_000);
        $other = FinFund::query()->create(['tenant_id' => $tenant->id, 'code' => 'OTH', 'name' => 'Other']);
        $account = FinAccount::query()->where('tenant_id', $tenant->id)->where('code', '6300')->firstOrFail();
        $bank = app(ChartOfAccounts::class)->bySystemKey($tenant->id, ChartOfAccounts::BANK);
        $expenses = app(ExpenseService::class);

        $expenses->record($tenant->id, 250_000, Carbon\Carbon::parse('2026-03-10'), $account->id, $bank->id, null, ['fund_id' => $grant->fund_id]);
        $expenses->record($tenant->id, 40_000, Carbon\Carbon::parse('2026-03-11'), $account->id, $bank->id, null, ['fund_id' => $other->id]);
        $expenses->record($tenant->id, 90_000, Carbon\Carbon::parse('2025-12-31'), $account->id, $bank->id, null, ['fund_id' => $grant->fund_id]);

        $position = app(GrantService::class)->position($grant->fresh());
        expect($position['spent'])->toBe(250_000)->and($position['remaining'])->toBe(750_000)->and($position['spent_percent'])->toBe(25)
            ->and($position['received'])->toBe(0)->and($position['outstanding'])->toBe(1_000_000);
    });
});

describe('Grants screens', function (): void {
    test('the screens add a funder and grant, plan and receive an instalment, and track a report', function (): void {
        [$user, $tenant] = createGrantsContext();
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('grants.index'))->assertOk()->assertSee('Open grants');
        $this->post(route('grants.funders.store'), ['name' => 'Wellspring', 'type' => 'foundation'])->assertRedirect();
        $funder = GrtFunder::query()->firstOrFail();
        $this->post(route('grants.grants.store'), ['funder_id' => $funder->id, 'title' => 'Water', 'total_amount' => '5000', 'status' => 'awarded', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->assertRedirect();
        $grant = GrtGrant::query()->firstOrFail();
        expect($grant->total_amount)->toBe(500_000);

        $this->post(route('grants.grants.instalments.store', $grant->uuid), ['expected_on' => today()->toDateString(), 'amount' => '2000'])->assertSessionHas('success');
        $instalment = GrtInstalment::query()->firstOrFail();
        $this->post(route('grants.grants.instalments.receive', $instalment->uuid), ['date' => today()->toDateString()])->assertSessionHas('success');
        expect($instalment->fresh()->status)->toBe('received');

        $this->post(route('grants.grants.reports.store', $grant->uuid), ['title' => 'Six-month report', 'due_on' => today()->addDays(5)->toDateString()])->assertSessionHas('success');
        $report = GrtReport::query()->firstOrFail();
        $this->get(route('grants.grants.show', $grant->uuid))->assertOk()->assertSee('Six-month report')->assertSee('Still to receive');
        $this->post(route('grants.grants.reports.submit', $report->uuid))->assertRedirect();
        expect($report->fresh()->status)->toBe('submitted');

        $this->post(route('grants.grants.status', $grant->uuid), ['status' => 'closed'])->assertRedirect();
        $this->get(route('grants.grants.index'))->assertOk()->assertSee('Water');
        $this->get(route('grants.funders.index'))->assertOk()->assertSee('Wellspring');
    });

    test('validation and permissions are enforced', function (): void {
        [$user, $tenant] = createGrantsContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('grants.grants.store'), ['total_amount' => '0'])->assertSessionHasErrors(['funder_id', 'title', 'total_amount']);
        $this->post(route('grants.funders.store'), [])->assertSessionHasErrors('name');

        $user->revokePermissionTo(['grants.manage', 'grants.record']);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('grants.index'))->assertOk();
        $this->post(route('grants.funders.store'), ['name' => 'X', 'type' => 'foundation'])->assertForbidden();

        $user->revokePermissionTo('grants.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('grants.index'))->assertForbidden();
    });
});

describe('Grants demo seeder', function (): void {
    test('creates funders, grants, instalments and spending that balance, once, and refuses production', function (): void {
        [, $tenant] = createGrantsContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(GrantsDemoSeeder::class);

        expect(GrtFunder::query()->count())->toBe(3)->and(GrtGrant::query()->count())->toBe(3)
            ->and(GrtInstalment::query()->where('status', 'received')->count())->toBe(2)
            ->and(GrtReport::query()->count())->toBe(3);
        $trial = app(FinanceReports::class)->trialBalance($tenant->id);
        expect($trial['debit'])->toBe($trial['credit']);

        $count = GrtInstalment::query()->count();
        $this->seed(GrantsDemoSeeder::class);
        expect(GrtInstalment::query()->count())->toBe($count);

        GrtReport::query()->delete();
        GrtInstalment::query()->delete();
        GrtGrant::query()->delete();
        GrtFunder::query()->delete();
        app()->detectEnvironment(fn (): string => 'production');
        (new GrantsDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(GrtFunder::query()->count())->toBe(0);
    });
});

describe('Tenant dashboard', function (): void {
    test('shows a grants card with live figures only to people who may see grants', function (): void {
        [$user, $tenant] = createGrantsContext();
        Permission::firstOrCreate(['name' => 'core.dashboard.access', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'dashboard']);
        $user->givePermissionTo('core.dashboard.access');
        grantOrganisationAdminAccess($user);
        app(ChartOfAccounts::class)->ensureSeeded($tenant->id);
        $service = app(GrantService::class);
        $service->receive($service->addInstalment(awardedGrant($tenant, 800_000), 300_000, today()), today());
        $url = route('tenant.dashboard', ['subdomain' => $tenant->slug]);

        $cards = $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])->get($url)->assertSuccessful()->viewData('moduleCards');
        expect($cards['grants']['kpis'][0]['value'])->toBe('GHS 8,000.00')->and($cards['grants']['kpis'][1]['value'])->toBe('GHS 3,000.00');

        $user->revokePermissionTo('grants.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        expect($this->get($url)->assertSuccessful()->viewData('moduleCards'))->not->toHaveKey('grants');
    });
});
