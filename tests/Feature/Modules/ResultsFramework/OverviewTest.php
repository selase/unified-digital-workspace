<?php

declare(strict_types=1);

use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\ProgrammeService;
use App\Modules\ResultsFramework\Services\TargetService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/fixtures.php';

test('viewers see active verdicts, capped programme averages and empty programmes', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $programmes = app(ProgrammeService::class);
    $programme = $programmes->create($tenant->id, 'Water');
    $programmes->create($tenant->id, 'Empty programme');
    $service = app(IndicatorService::class);
    $today = CarbonImmutable::today();
    foreach ([['Done', 12, 10, 'active'], ['Steady', 5, 10, 'active'], ['Late', 1, 10, 'active'], ['Closed', 5, -1, 'active'], ['Archived', 10, 10, 'archived']] as [$name, $value, $end, $status]) {
        $indicator = $service->create($tenant->id, $name, 10, $today->subDays(10), $today->addDays($end), ['programme_id' => $programme->id]);
        $service->record($indicator, $value, $today->subDays(2), null, null);
        $indicator->update(['status' => $status]);
    }

    $user->revokePermissionTo('results.manage');
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $this->get(route('results-framework.index'))->assertOk()->assertViewIs('results-framework::overview')
        ->assertViewHas('counts', fn ($counts): bool => $counts->only(['achieved', 'on_track', 'behind', 'missed'])->all() === ['achieved' => 1, 'on_track' => 1, 'behind' => 1, 'missed' => 1])
        ->assertSee('Water')->assertSee('53%')->assertSee('Empty programme')->assertSee('No active indicators');
    $this->get(route('results-framework.indicators.index'))->assertOk()->assertSee('Done');
    $user->revokePermissionTo('results.view');
    $this->get(route('results-framework.index'))->assertForbidden();
});

test('manual chart data includes readings, target and elapsed time', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $service = app(IndicatorService::class);
    $start = CarbonImmutable::today()->subDays(10);
    $end = $start->addDays(20);
    $indicator = $service->create($tenant->id, 'Manual', 10, $start, $end);
    $service->record($indicator, 4, $start->addDays(5), null, null);
    $service->record($indicator, 2, $start, null, null);
    expect($service->history($indicator, $user))->toBe([
        ['date' => $start->toDateString(), 'value' => 2.0],
        ['date' => $start->addDays(5)->toDateString(), 'value' => 4.0],
    ]);
    $user->revokePermissionTo('results.manage');
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('results-framework.indicators.show', $indicator->uuid))->assertOk()->assertSee('indicator-trend-chart')
        ->assertViewHas('trend', fn (array $trend): bool => array_column($trend['datasets'], 'label') === ['Readings', 'Target', 'Time elapsed']
            && $trend['datasets'][0]['data'] === [2.0, 4.0, null]
            && $trend['datasets'][1]['data'] === [10.0, 10.0, 10.0]
            && $trend['datasets'][2]['data'] === [0, 25, 100]);
});

test('chart history and programme averages preserve restricted period and total protection', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $programme = app(ProgrammeService::class)->create($tenant->id, 'Protected reach');
    $start = CarbonImmutable::today()->subDays(5);
    $end = $start->addDays(4);
    foreach ([$start, $start, $end, $end, $end] as $date) {
        $person = BenBeneficiary::query()->create(['tenant_id' => $tenant->id, 'full_name' => 'Test person']);
        $person->services()->create(['tenant_id' => $tenant->id, 'type' => $date === $start ? 'food' : 'medical', 'delivered_on' => $date]);
    }
    $service = app(IndicatorService::class);
    $indicator = $service->create($tenant->id, 'Overall reach', 10, $start, $end, ['measure_key' => 'beneficiaries.people_reached', 'programme_id' => $programme->id]);
    $small = $service->create($tenant->id, 'Filtered reach', 10, $start, $end, ['measure_key' => 'beneficiaries.people_reached', 'programme_id' => $programme->id, 'measure_filters' => ['service_type' => 'food']]);
    $targets = app(TargetService::class);
    $targets->set($indicator, $start, $start->addDay(), 4);
    $targets->set($indicator, $start->addDays(2), $end, 6);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $show = route('results-framework.indicators.show', $indicator->uuid);
    expect($service->history($indicator, $user))->toBe([]);
    $this->get($show)->assertOk()->assertDontSee('indicator-trend-chart')->assertViewHas('trend', []);
    $this->get(route('results-framework.index'))->assertOk()->assertSee('Protected reach')
        ->assertSee('Hidden to protect small groups')->assertDontSee('35%');
    Permission::firstOrCreate(['name' => 'beneficiaries.view', 'guard_name' => 'web'], ['uuid' => (string) Str::uuid(), 'category' => 'beneficiaries']);
    $user->givePermissionTo('beneficiaries.view');
    expect(array_column($service->history($indicator, $user), 'value'))->toBe([2.0, 3.0]);
    $this->get($show)->assertOk()->assertSee('indicator-trend-chart');
    expect($service->history($small, $user))->toBe([]);
    $this->get(route('results-framework.index'))->assertOk()->assertSee('35%');
});
