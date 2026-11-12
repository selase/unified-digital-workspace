<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Modules\ResultsFramework\Database\Seeders\ResultsDemoSeeder;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Services\IndicatorService;
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

    $permissions = config('modules.results-framework.permissions');
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'], ['category' => 'results', 'uuid' => (string) Str::uuid()]);
    }
    $user->givePermissionTo($permissions);

    app(TenantDatabaseManager::class)->configure($tenant);
    TenantFeature::query()->create(['tenant_id' => $tenant->id, 'feature_key' => 'module-results-framework', 'enabled' => true, 'meta' => ['source' => 'complimentary']]);
    app(ModuleManager::class)->enableForTenant('results-framework', $tenant);
    Artisan::call('migrate', ['--database' => 'tenant', '--path' => app_path('Modules/ResultsFramework/Database/Migrations'), '--realpath' => true, '--force' => true]);

    return [$user, $tenant];
}

function indicator(Tenant $tenant, array $overrides = []): ResultIndicator
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

        $this->get(route('results-framework.index'))->assertOk()->assertSee('Results Framework');
        $programme = app(App\Modules\ResultsFramework\Services\ProgrammeService::class)->create($tenant->id, 'Water');
        $this->post(route('results-framework.indicators.store'), ['name' => 'Wells built', 'programme_id' => $programme->id, 'unit' => 'wells', 'source' => 'manual', 'baseline' => 0, 'target' => 12, 'starts_on' => today()->startOfYear()->toDateString(), 'ends_on' => today()->endOfYear()->toDateString()])->assertRedirect();
        $model = ResultIndicator::query()->firstOrFail();

        $this->get(route('results-framework.indicators.show', $model->uuid))->assertOk()->assertSee('Wells built')->assertSee('Add a reading');
        $this->post(route('results-framework.indicators.readings.store', $model->uuid), ['value' => 3, 'recorded_on' => today()->toDateString(), 'note' => 'First three'])->assertSessionHas('success');
        $this->get(route('results-framework.index'))->assertSee('Wells built')->assertSee('25% of target');

        $csv = $this->get(route('results-framework.report'));
        $csv->assertOk()->assertDownload();
        expect($csv->streamedContent())->toContain('Wells built', 'Water');
        $this->get(route('results-framework.report', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->post(route('results-framework.indicators.toggle', $model->uuid))->assertSessionHas('success');
        expect($model->fresh()->status)->toBe('archived');
        $this->post(route('results-framework.indicators.readings.store', $model->uuid), ['value' => 4, 'recorded_on' => today()->toDateString()])->assertSessionHas('error');
    });

    test('validation and permissions are enforced and one organisation cannot see another\'s indicators', function (): void {
        [$user, $tenant] = createMonitoringContext();
        $foreign = ResultIndicator::query()->create(['tenant_id' => (string) Tenant::factory()->create()->id, 'name' => 'Other org', 'target' => 10, 'starts_on' => today(), 'ends_on' => today()->addDay()]);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);

        $this->post(route('results-framework.indicators.store'), [])->assertSessionHasErrors(['name', 'target', 'starts_on', 'ends_on']);
        $this->get(route('results-framework.indicators.show', $foreign->uuid))->assertNotFound();

        $user->revokePermissionTo('results.manage');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->post(route('results-framework.indicators.store'), ['name' => 'X', 'target' => 1, 'source' => 'manual', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDay()->toDateString()])->assertForbidden();
        $this->get(route('results-framework.index'))->assertOk();

        $user->revokePermissionTo('results.view');
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->get(route('results-framework.index'))->assertForbidden();
    });

    test('the demo seeder creates indicators once and refuses production', function (): void {
        [, $tenant] = createMonitoringContext();
        config(['app.demo_tenant' => $tenant->slug]);

        $this->seed(ResultsDemoSeeder::class);
        expect(ResultIndicator::query()->count())->toBe(5);
        $this->seed(ResultsDemoSeeder::class);
        expect(ResultIndicator::query()->count())->toBe(5);

        ResultIndicator::query()->delete();
        app()->detectEnvironment(fn (): string => 'production');
        (new ResultsDemoSeeder)->run();
        app()->detectEnvironment(fn (): string => 'testing');
        expect(ResultIndicator::query()->count())->toBe(0);
    });
});

test('the results framework has its new module, navigation, route and package names', function (): void {
    $module = app(ModuleManager::class)->find('results-framework');
    $navigation = new ReflectionMethod(App\Services\Navigation\WorkspaceNavigation::class, 'moduleDefinitions');

    expect($module['slug'] ?? null)->toBe('results-framework')
        ->and($module['name'] ?? null)->toBe('Results Framework')
        ->and($navigation->invoke(app(App\Services\Navigation\WorkspaceNavigation::class)))->toHaveKey('results-framework')
        ->and(Route::has('results-framework.index'))->toBeTrue()
        ->and(Route::has('programme-results.index'))->toBeFalse()
        ->and(config('usage-pricing.hybrid.packages.results_framework.modules'))->toBe(['results-framework'])
        ->and(config('usage-pricing.hybrid.packages.programme_results'))->toBeNull();
});

test('legacy results bookmarks redirect permanently to the corresponding results framework page', function (): void {
    [$user] = createMonitoringContext();

    $this->actingAs($user)->get('/programme-results')->assertRedirect('/results-framework')->assertStatus(301);
    $this->get('/programme-results/indicators/1')->assertRedirect('/results-framework/indicators/1')->assertStatus(301);
});

test('indicator edits round trip the definition and verification and preserve decreasing progress', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $service = app(IndicatorService::class);
    $model = indicator($tenant, ['name' => 'Malaria cases', 'baseline' => 400, 'target' => 100]);
    $service->record($model, 250, today()->subDay(), null, null);
    expect($service->progress($model)['status'])->toBe('on_track');
    $service->record($model, 100, today(), null, null);
    expect($service->progress($model)['status'])->toBe('achieved');

    $programme = app(App\Modules\ResultsFramework\Services\ProgrammeService::class)->create($tenant->id, 'Health');
    $node = app(App\Modules\ResultsFramework\Services\ResultChainService::class)->add($programme, 'outcome', 'Fewer cases');
    $fields = [
        'name' => 'Confirmed malaria cases', 'description' => 'Reported cases',
        'definition' => 'Confirmed cases in the programme period',
        'means_of_verification' => 'Lab register and routine surveillance report',
        'programme_id' => $programme->id, 'result_id' => $node->id, 'unit' => 'cases',
        'source' => 'manual', 'baseline' => 400, 'target' => 100,
        'starts_on' => today()->subDays(100)->toDateString(), 'ends_on' => today()->addDays(100)->toDateString(),
    ];

    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.indicators.edit', $model->uuid))->assertOk()->assertSee('Edit indicator');
    $this->put(route('results-framework.indicators.update', $model->uuid), $fields)->assertRedirect(route('results-framework.indicators.show', $model->uuid));
    $model->refresh();
    expect($model->only(array_keys(array_diff_key($fields, array_flip(['starts_on', 'ends_on', 'baseline', 'target'])))))
        ->toEqual(array_diff_key($fields, array_flip(['starts_on', 'ends_on', 'baseline', 'target'])))
        ->and($model->starts_on->toDateString())->toBe($fields['starts_on'])
        ->and($model->ends_on->toDateString())->toBe($fields['ends_on'])
        ->and($model->baseline)->toBe(400.0)->and($model->target)->toBe(100.0)
        ->and($model->getConnection()->getSchemaBuilder()->hasColumn('rf_indicators', 'programme'))->toBeFalse();
    $this->get(route('results-framework.indicators.show', $model->uuid))
        ->assertOk()->assertSee($fields['definition'])->assertSee($fields['means_of_verification']);
    expect(fn () => $service->update($model, ['source' => 'donations_total']))->toThrow(DomainException::class)
        ->and(fn () => $service->update($model->fresh(), ['target' => 400]))->toThrow(DomainException::class);
});

test('indicator editing requires manage permission and returns 404 for another tenant', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $model = indicator($tenant);
    $foreign = ResultIndicator::query()->create([
        'tenant_id' => Tenant::factory()->create()->id, 'name' => 'Foreign indicator',
        'target' => 20, 'starts_on' => today(), 'ends_on' => today()->addDay(),
    ]);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.indicators.edit', $foreign->uuid))->assertNotFound();
    $user->revokePermissionTo('results.manage');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->get(route('results-framework.indicators.edit', $model->uuid))->assertForbidden();
    $this->put(route('results-framework.indicators.update', $model->uuid), [])->assertForbidden();
});
