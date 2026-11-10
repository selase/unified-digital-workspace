<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\MonitoringEvaluation\Database\Seeders\MonitoringDemoSeeder;
use App\Modules\MonitoringEvaluation\Models\MelIndicator;
use App\Modules\MonitoringEvaluation\Services\IndicatorService;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createMonitoringContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = config('modules.monitoring-evaluation.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'monitoring', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-monitoring-evaluation', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('monitoring-evaluation', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/MonitoringEvaluation/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant];
}

function indicator(Tenant $tenant, array $overrides = []): MelIndicator
{
    $start = $overrides['starts_on'] ?? CarbonImmutable::today()->subDays(100);
    $end = $overrides['ends_on'] ?? CarbonImmutable::today()->addDays(100);

    return app(IndicatorService::class)->create($tenant->id, $overrides['name'] ?? 'Children fed', $overrides['target'] ?? 1000, $start, $end, $overrides + ['baseline' => 0]);
}

describe('Indicator rules', function (): void {
    test('creation is validated and a manual indicator uses its latest reading', function (): void {
        [, $tenant] = createMonitoringContext();
        $service = app(IndicatorService::class);

        expect(fn () => $service->create($tenant->id, 'Bad dates', 10, CarbonImmutable::today(), CarbonImmutable::today()->subDay()))->toThrow(DomainException::class, 'end after')
            ->and(fn () => $service->create($tenant->id, 'Same', 10, CarbonImmutable::today(), CarbonImmutable::today()->addDay(), ['baseline' => 10]))->toThrow(DomainException::class, 'different')
            ->and(fn () => $service->create($tenant->id, 'Bad source', 10, CarbonImmutable::today(), CarbonImmutable::today()->addDay(), ['source' => 'nonsense']))->toThrow(DomainException::class, 'where the figure');

        $indicator = indicator($tenant);
        expect($service->current($indicator))->toBe(0.0);
        $service->record($indicator, 300, CarbonImmutable::today()->subDays(30), null, null);
        $service->record($indicator, 450, CarbonImmutable::today()->subDays(2), 'After the term started', null);
        expect($service->current($indicator))->toBe(450.0)->and($service->progress($indicator)['percent'])->toBe(45)
            ->and(fn () => $service->record($indicator, 5, CarbonImmutable::today()->addDay(), null, null))->toThrow(DomainException::class, 'future');

        $indicator->update(['status' => 'archived']);
        expect(fn () => $service->record($indicator->fresh(), 5, CarbonImmutable::today(), null, null))->toThrow(DomainException::class, 'Reactivate');
    });

    test('status is achieved, on track, behind or missed', function (): void {
        [, $tenant] = createMonitoringContext();
        $service = app(IndicatorService::class);
        $today = CarbonImmutable::today();

        $achieved = indicator($tenant, ['name' => 'A']);
        $service->record($achieved, 1000, $today, null, null);
        $onTrack = indicator($tenant, ['name' => 'B']);
        $service->record($onTrack, 450, $today, null, null);
        $behind = indicator($tenant, ['name' => 'C']);
        $service->record($behind, 100, $today, null, null);
        $missed = indicator($tenant, ['name' => 'D', 'starts_on' => $today->subDays(60), 'ends_on' => $today->subDay()]);
        $service->record($missed, 200, $today->subDays(5), null, null);

        expect($service->progress($achieved)['status'])->toBe('achieved')->and($service->progress($onTrack)['status'])->toBe('on_track')
            ->and($service->progress($behind)['status'])->toBe('behind')->and($service->progress($missed)['status'])->toBe('missed');
    });

    test('calculated indicators read other modules and refuse hand-entered readings', function (): void {
        [, $tenant] = createMonitoringContext();
        $service = app(IndicatorService::class);
        $reached = indicator($tenant, ['name' => 'Reached', 'source' => 'beneficiaries_reached']);

        expect($service->current($reached))->toBe(0.0)
            ->and(fn () => $service->record($reached, 5, CarbonImmutable::today(), null, null))->toThrow(DomainException::class, 'calculated');

        foreach (['ben_services' => ['beneficiary_id', 'delivered_on'], 'vol_hours' => ['volunteer_id', 'worked_on'], 'don_donations' => ['donor_id', 'date']] as $table => $columns) {
            Illuminate\Support\Facades\Schema::connection('tenant')->dropIfExists($table);
        }
        Illuminate\Support\Facades\Schema::connection('tenant')->create('ben_services', function ($t): void {
            $t->id();
            $t->uuid('tenant_id');
            $t->unsignedBigInteger('beneficiary_id');
            $t->date('delivered_on');
        });
        Illuminate\Support\Facades\Schema::connection('tenant')->create('vol_hours', function ($t): void {
            $t->id();
            $t->uuid('tenant_id');
            $t->unsignedBigInteger('volunteer_id');
            $t->date('worked_on');
            $t->integer('minutes');
            $t->string('status');
        });
        Illuminate\Support\Facades\Schema::connection('tenant')->create('don_donations', function ($t): void {
            $t->id();
            $t->uuid('tenant_id');
            $t->unsignedBigInteger('donor_id');
            $t->date('date');
            $t->bigInteger('amount');
            $t->timestamp('voided_at')->nullable();
        });
        $today = CarbonImmutable::today()->toDateString();
        foreach ([1, 1, 2, 3] as $person) {
            DB::connection('tenant')->table('ben_services')->insert(['tenant_id' => $tenant->id, 'beneficiary_id' => $person, 'delivered_on' => $today]);
        }
        DB::connection('tenant')->table('ben_services')->insert(['tenant_id' => $tenant->id, 'beneficiary_id' => 9, 'delivered_on' => CarbonImmutable::today()->subYear()->toDateString()]);
        DB::connection('tenant')->table('vol_hours')->insert([
            ['tenant_id' => $tenant->id, 'volunteer_id' => 1, 'worked_on' => $today, 'minutes' => 150, 'status' => 'approved'],
            ['tenant_id' => $tenant->id, 'volunteer_id' => 1, 'worked_on' => $today, 'minutes' => 600, 'status' => 'pending'],
        ]);
        DB::connection('tenant')->table('don_donations')->insert([
            ['tenant_id' => $tenant->id, 'donor_id' => 1, 'date' => $today, 'amount' => 25050, 'voided_at' => null],
            ['tenant_id' => $tenant->id, 'donor_id' => 1, 'date' => $today, 'amount' => 90000, 'voided_at' => now()],
        ]);

        expect($service->current($reached))->toBe(3.0)
            ->and($service->current(indicator($tenant, ['name' => 'Hours', 'source' => 'volunteer_hours'])))->toBe(2.5)
            ->and($service->current(indicator($tenant, ['name' => 'Money', 'source' => 'donations_total'])))->toBe(250.5);
    });
});

describe('Indicator screens', function (): void {
    test('staff create an indicator, add a reading, archive it and download the report', function (): void {
        [$user, $tenant] = createMonitoringContext();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->get(route('monitoring-evaluation.index'))->assertOk()->assertSee('Monitoring & Evaluation');
        $this->post(route('monitoring-evaluation.indicators.store'), ['name' => 'Wells built', 'programme' => 'Water', 'unit' => 'wells', 'source' => 'manual', 'baseline' => 0, 'target' => 12, 'starts_on' => today()->startOfYear()->toDateString(), 'ends_on' => today()->endOfYear()->toDateString()])->assertRedirect();
        $model = MelIndicator::query()->firstOrFail();

        $this->get(route('monitoring-evaluation.indicators.show', $model->uuid))->assertOk()->assertSee('Wells built')->assertSee('Add a reading');
        $this->post(route('monitoring-evaluation.indicators.readings.store', $model->uuid), ['value' => 3, 'recorded_on' => today()->toDateString(), 'note' => 'First three'])->assertSessionHas('success');
        $this->get(route('monitoring-evaluation.index'))->assertSee('Wells built')->assertSee('25% of target');

        $csv = $this->get(route('monitoring-evaluation.report'));
        $csv->assertOk()->assertDownload();
        expect($csv->streamedContent())->toContain('Wells built', 'Water');
        $this->get(route('monitoring-evaluation.report', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->post(route('monitoring-evaluation.indicators.toggle', $model->uuid))->assertSessionHas('success');
        expect($model->fresh()->status)->toBe('archived');
        $this->post(route('monitoring-evaluation.indicators.readings.store', $model->uuid), ['value' => 4, 'recorded_on' => today()->toDateString()])->assertSessionHas('error');
    });

    test('validation and permissions are enforced and one organisation cannot see another\'s indicators', function (): void {
        [$user, $tenant] = createMonitoringContext();
        $foreign = MelIndicator::query()->create(['tenant_id' => (string) Tenant::factory()->create()->id, 'name' => 'Other org', 'target' => 10, 'starts_on' => today(), 'ends_on' => today()->addDay()]);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('monitoring-evaluation.indicators.store'), [])->assertSessionHasErrors(['name', 'target', 'starts_on', 'ends_on']);
        $this->get(route('monitoring-evaluation.indicators.show', $foreign->uuid))->assertNotFound();

        $user->revokePermissionTo('monitoring.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('monitoring-evaluation.indicators.store'), ['name' => 'X', 'target' => 1, 'source' => 'manual', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDay()->toDateString()])->assertForbidden();
        $this->get(route('monitoring-evaluation.index'))->assertOk();

        $user->revokePermissionTo('monitoring.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('monitoring-evaluation.index'))->assertForbidden();
    });

    test('the demo seeder creates indicators once and refuses production', function (): void {
        [, $tenant] = createMonitoringContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(MonitoringDemoSeeder::class);
        expect(MelIndicator::query()->count())->toBe(5);
        $this->seed(MonitoringDemoSeeder::class);
        expect(MelIndicator::query()->count())->toBe(5);

        MelIndicator::query()->delete();
        app()->detectEnvironment(fn (): string => 'production');
        (new MonitoringDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(MelIndicator::query()->count())->toBe(0);
    });
});
