<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\MeasureRegistry;
use App\Modules\ResultsFramework\Services\TargetService;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/fixtures.php';

test('period targets must fit within the indicator and cannot share dates', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $indicators = app(IndicatorService::class);
    $start = CarbonImmutable::parse('2025-01-01');
    $end = $start->endOfYear()->startOfDay();
    $indicator = $indicators->create($tenant->id, 'Annual', 100, $start, $end);
    $targets = app(TargetService::class);
    $first = $targets->set($indicator, $start, $start->endOfMonth(), 10);

    expect(fn () => $targets->set($indicator, $start->subDay(), $start, 5))->toThrow(DomainException::class)
        ->and(fn () => $targets->set($indicator, $start->endOfMonth(), $start->addMonth(), 5))->toThrow(DomainException::class)
        ->and(fn () => $targets->set($indicator, $start->addMonth(), $start, 5))->toThrow(DomainException::class)
        ->and(fn () => $indicators->update($indicator, ['starts_on' => $start->addDay()]))->toThrow(DomainException::class)
        ->and($targets->set($indicator, $start, $start->endOfMonth(), 15)->id)->toBe($first->id)
        ->and($targets->forIndicator($indicator)->sole()->target_value)->toBe(15.0)
        ->and($indicators->progress($indicator, $user)['periods'][0]['status'])->toBe('no_reading');
});

test('generation clips calendar periods and distributes cents exactly without replacing edits', function (): void {
    [, $tenant] = createMonitoringContext();
    $indicators = app(IndicatorService::class);
    $start = CarbonImmutable::parse('2025-01-01');
    $indicator = $indicators->create($tenant->id, 'Annual', 100.01, $start, $start->endOfYear());
    $targets = app(TargetService::class);

    $quarters = $targets->generate($indicator, 'quarterly');
    expect($quarters)->toHaveCount(4)
        ->and($quarters->pluck('target_value')->all())->toBe([25.01, 25.0, 25.0, 25.0])
        ->and(round($quarters->sum('target_value'), 2))->toBe(100.01);
    expect(fn () => $targets->generate($indicator, 'monthly'))->toThrow(DomainException::class);
    expect($targets->forIndicator($indicator))->toHaveCount(4);

    $partial = $indicators->create($tenant->id, 'Partial', 30, $start->addDays(14), $start->addMonths(2)->addDays(9));
    $months = $targets->generate($partial, 'monthly');
    expect($months->map(fn ($target): array => [$target->period_start->toDateString(), $target->period_end->toDateString()])->all())
        ->toBe([['2025-01-15', '2025-01-31'], ['2025-02-01', '2025-02-28'], ['2025-03-01', '2025-03-10']]);
});

test('per-period progress uses the latest reading inside the period and leaves overall progress intact', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $service = app(IndicatorService::class);
    $start = CarbonImmutable::parse('2025-01-01');
    $indicator = $service->create($tenant->id, 'Manual', 10, $start, $start->endOfQuarter());
    $service->record($indicator, 1, $start, null, null);
    $service->record($indicator, 4, $start->addDays(5), null, null);
    $service->record($indicator, 8, $start->addMonth(), null, null);
    $overall = $service->progress($indicator, $user);
    expect($overall['periods'])->toBe([]);

    app(TargetService::class)->set($indicator, $start, $start->endOfMonth(), 5);
    $periods = $service->periodProgress($indicator, $user);
    expect($periods)->toHaveCount(1)
        ->and($periods[0]['achieved'])->toBe(4.0)
        ->and($periods[0]['percent'])->toBe(80)
        ->and($service->progress($indicator, $user)['current'])->toBe($overall['current']);
});

test('live period figures use registry dates and apply the same privacy as overall progress', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $start = CarbonImmutable::parse('2025-01-01');
    $january = $start->endOfMonth()->startOfDay();
    $february = $january->addMonthNoOverflow()->endOfMonth()->startOfDay();
    foreach ([$start, $january, $february, $february, $february] as $date) {
        $beneficiary = BenBeneficiary::query()->create(['tenant_id' => $tenant->id, 'full_name' => 'Person']);
        $beneficiary->services()->create(['tenant_id' => $tenant->id, 'type' => 'food', 'delivered_on' => $date]);
    }
    $service = app(IndicatorService::class);
    $indicator = $service->create($tenant->id, 'Reach', 10, $start, $february, ['measure_key' => 'beneficiaries.people_reached']);
    app(TargetService::class)->set($indicator, $start, $january, 4);
    app(TargetService::class)->set($indicator, $january->addDay(), $february, 4);
    expect($service->periodProgress($indicator, $user)[0])->toMatchArray(['achieved' => '<3', 'percent' => null, 'status' => 'restricted']);
    expect($service->periodProgress($indicator, $user)[1])->toMatchArray(['achieved' => null, 'percent' => null, 'status' => 'restricted']);

    Permission::firstOrCreate(['name' => 'beneficiaries.view', 'guard_name' => 'web'], ['category' => 'beneficiaries', 'uuid' => (string) Str::uuid()]);
    $user->givePermissionTo('beneficiaries.view');
    $registryValue = app(MeasureRegistry::class)->value($tenant, $indicator->measure_key, new MeasureQuery($start, $january));
    expect($service->periodProgress($indicator, $user)[0])->toMatchArray(['achieved' => $registryValue, 'percent' => 50]);
});

test('target forms persist through the indicator page and reject cross-tenant writes', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $start = CarbonImmutable::parse('2025-01-01');
    $indicator = app(IndicatorService::class)->create($tenant->id, 'Target form', 10, $start, $start->endOfQuarter());
    $foreign = ResultIndicator::query()->create([
        'tenant_id' => Tenant::factory()->create()->id, 'name' => 'Other', 'target' => 10,
        'starts_on' => $start, 'ends_on' => $start->endOfQuarter(),
    ]);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $show = route('results-framework.indicators.show', $indicator->uuid);
    $this->get($show)->assertOk()->assertSee('Period targets');
    $this->post(route('results-framework.indicators.targets.store', $indicator->uuid), [
        'period_start' => $start->toDateString(), 'period_end' => $start->endOfMonth()->toDateString(), 'target_value' => 4,
    ])->assertRedirect($show)->assertSessionHas('success');
    $this->post(route('results-framework.indicators.targets.generate', $foreign->uuid), ['frequency' => 'monthly'])->assertNotFound();
    $this->get($show)->assertOk()->assertSee($start->toInstitutionalDate().' to '.$start->endOfMonth()->toInstitutionalDate());
    $generated = app(IndicatorService::class)->create($tenant->id, 'Generated', 12, $start, $start->endOfQuarter());
    $generatedUrl = route('results-framework.indicators.targets.generate', $generated->uuid);
    $this->post($generatedUrl, ['frequency' => 'weekly'])->assertSessionHasErrors('frequency');
    $this->post($generatedUrl, ['frequency' => 'monthly'])
        ->assertRedirect(route('results-framework.indicators.show', $generated->uuid))->assertSessionHas('success');
    expect(app(TargetService::class)->forIndicator($generated))->toHaveCount(3);
    $user->revokePermissionTo('results.manage');
    $this->post(route('results-framework.indicators.targets.generate', $indicator->uuid), ['frequency' => 'monthly'])->assertForbidden();
});

test('rounded progress cannot mark a missed target as achieved', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $service = app(IndicatorService::class);
    $start = today()->subMonth();
    $end = today()->subDay();
    $indicator = $service->create($tenant->id, 'Precise target', 100, $start, $end);
    app(TargetService::class)->set($indicator, $start, $end, 100);
    $service->record($indicator, 99.6, $end, null, null);
    expect($service->periodProgress($indicator, $user)[0])->toMatchArray(['percent' => 99, 'status' => 'missed']);
    $service->record($indicator, 100, $end, null, null);
    expect($service->periodProgress($indicator, $user)[0])->toMatchArray(['percent' => 100, 'status' => 'achieved']);
});
