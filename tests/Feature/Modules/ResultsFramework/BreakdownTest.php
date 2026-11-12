<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\ResultsFramework\Services\BreakdownService;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\MeasureRegistry;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

require_once __DIR__.'/fixtures.php';

function makeBreakdownBeneficiary(Tenant $tenant, array $attributes, string $serviceType = 'food'): BenBeneficiary
{
    $person = BenBeneficiary::query()->create($attributes + ['tenant_id' => $tenant->id, 'full_name' => 'Test person', 'enrolled_on' => '2026-01-01']);
    $person->services()->create(['tenant_id' => $tenant->id, 'delivered_on' => '2026-01-01', 'type' => $serviceType]);

    return $person;
}

test('beneficiary breakdowns use the same population as each measure and age at period end', function (): void {
    [, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $other = Tenant::factory()->create();
    $child = makeBreakdownBeneficiary($tenant, ['gender' => 'female', 'date_of_birth' => '2008-02-01', 'region' => 'North', 'programme' => 'Water']);
    $child->services()->create(['tenant_id' => $tenant->id, 'delivered_on' => '2026-01-31', 'type' => 'food']);
    makeBreakdownBeneficiary($tenant, ['gender' => 'male', 'date_of_birth' => '2008-01-31', 'region' => 'South', 'programme' => 'Water']);
    makeBreakdownBeneficiary($tenant, ['gender' => 'other', 'date_of_birth' => '1966-01-31', 'region' => 'North', 'programme' => 'Water']);
    makeBreakdownBeneficiary($tenant, ['programme' => 'Water']);
    makeBreakdownBeneficiary($tenant, ['gender' => 'female', 'programme' => 'Food'], 'medical');
    makeBreakdownBeneficiary($other, ['gender' => 'female', 'programme' => 'Water']);

    $registry = app(MeasureRegistry::class);
    $from = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');
    $water = new MeasureQuery($from, $end, ['programme' => 'Water', 'service_type' => 'food']);
    expect($registry->breakdown($tenant, 'beneficiaries.people_reached', 'gender', $water))
        ->toEqual(['female' => 1.0, 'male' => 1.0, 'other' => 1.0, 'unknown' => 1.0])
        ->and($registry->breakdown($tenant, 'beneficiaries.people_reached', 'age_band', $water))
        ->toEqual(['0-17' => 1.0, '18-34' => 1.0, '60+' => 1.0, 'unknown' => 1.0])
        ->and($registry->breakdown($tenant, 'beneficiaries.people_reached', 'location', $water))
        ->toEqual(['North' => 2.0, 'South' => 1.0, 'unknown' => 1.0])
        ->and($registry->breakdown($tenant, 'beneficiaries.services_delivered', 'gender', new MeasureQuery($from, $end, ['service_type' => 'food'])))
        ->toEqual(['female' => 2.0, 'male' => 1.0, 'other' => 1.0, 'unknown' => 1.0])
        ->and($registry->breakdown($tenant, 'beneficiaries.new_registrations', 'gender', new MeasureQuery($from, $end)))
        ->toEqual(['female' => 2.0, 'male' => 1.0, 'other' => 1.0, 'unknown' => 1.0]);
});

test('manual breakdowns validate each total and persist with the latest reading', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $service = app(IndicatorService::class);
    $indicator = $service->create($tenant->id, 'Manual count', 10, today()->subWeek(), today());
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $url = route('results-framework.indicators.readings.store', $indicator->uuid);
    $fields = [
        'value' => 5, 'recorded_on' => today()->toDateString(),
        'breakdowns' => [
            'gender' => [['bucket' => 'female', 'value' => 2], ['bucket' => 'male', 'value' => 3]],
            'location' => [['bucket' => 'North clinic', 'value' => 5]],
        ],
    ];
    $show = route('results-framework.indicators.show', $indicator->uuid);
    $this->from($show)->post($url, array_replace($fields, [
        'breakdowns' => ['gender' => [['bucket' => 'female', 'value' => ['invalid']]]],
    ]))->assertSessionHasErrors('breakdowns.gender.0.value');
    $this->get($show)->assertOk()->assertSee('Enter a numeric bucket figure.');
    $this->post($url, array_replace($fields, ['value' => 6]))->assertSessionHas('error');
    expect($indicator->readings()->count())->toBe(0);
    $this->post($url, $fields)->assertSessionHas('success');

    $breakdowns = app(BreakdownService::class);
    expect($breakdowns->forIndicator($indicator, $user, 'gender'))->toBe([])
        ->and($breakdowns->forIndicator($indicator, $user, 'location'))->toBe(['North clinic' => 5.0]);
    $this->get(route('results-framework.indicators.show', $indicator->uuid))->assertOk()->assertSee('North clinic')->assertSee('Hidden to protect small groups');
    $service->record($indicator, 8, today(), null, null, ['gender' => [
        ['bucket' => 'female', 'value' => 3], ['bucket' => 'male', 'value' => 5], ['bucket' => 'other', 'value' => 0],
    ]]);
    expect($breakdowns->forIndicator($indicator, $user, 'gender'))->toBe(['female' => 3.0, 'male' => 5.0, 'other' => 0.0]);
    $service->record($indicator, 8, today(), null, null);
    expect($breakdowns->forIndicator($indicator, $user, 'gender'))->toBe([]);
});

test('restricted totals and buckets stay masked in indicator views and exports', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $service = app(IndicatorService::class);
    $from = CarbonImmutable::parse('2026-01-01');
    $end = CarbonImmutable::parse('2026-01-31');
    foreach (['male', 'male', 'female', 'female', 'female'] as $gender) {
        makeBreakdownBeneficiary($tenant, ['gender' => $gender], $gender === 'male' ? 'food' : 'medical');
    }
    $indicator = $service->create($tenant->id, 'Filtered total', 10, $from, $end, [
        'measure_key' => 'beneficiaries.people_reached', 'measure_filters' => ['service_type' => 'food'],
    ]);
    $all = $service->create($tenant->id, 'All services', 10, $from, $end, ['measure_key' => 'beneficiaries.services_delivered']);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $show = route('results-framework.indicators.show', $indicator->uuid);
    $this->get($show)->assertOk()->assertSee('<3')->assertDontSee('20%')
        ->assertViewHas('progress', fn (array $progress): bool => $progress['current'] === '<3' && $progress['percent'] === null);
    $this->get(route('results-framework.indicators.index'))->assertOk()->assertSee('<3')->assertDontSee('20% of target')
        ->assertSee('Hidden to protect small groups');
    $this->get(route('results-framework.indicators.show', $all->uuid))->assertOk()
        ->assertSee('Hidden to protect small groups')->assertDontSee('Female')->assertDontSee('Male');
    $this->get(route('results-framework.report', ['format' => 'print']))->assertOk()
        ->assertSee('Hidden to protect small groups')->assertDontSee('Female:')->assertDontSee('Male:');
    expect(app(MeasureRegistry::class)->canSeeExactValue(null, $user))->toBeTrue();
    expect(app(BreakdownService::class)->forIndicator($all, $user, 'gender'))->toBe([]);
    $csv = $this->get(route('results-framework.report'))->assertOk()->streamedContent();
    expect($csv)->toContain('<3', 'Hidden to protect small groups')->not->toContain('Female:', 'Male:');

    $renderer = Pdf::getFacadeRoot();
    Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use ($renderer): mixed {
        $html = html_entity_decode(view($view, $data)->render());
        expect($html)->toContain('<3', 'Hidden to protect small groups')->not->toContain('Female:', 'Male:');

        return $renderer->loadView($view, $data);
    });
    $pdf = $this->get(route('results-framework.report', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $pdfContent = $pdf->getContent();
    expect($pdfContent)->toStartWith('%PDF');
    preg_match_all('/\bstream\r?\n(.*?)\r?\nendstream\b/s', $pdfContent, $matches);
    $streams = collect($matches[1])->map(fn (string $stream): string => @gzuncompress($stream) ?: $stream)->implode('');
    expect($streams)->toContain(mb_convert_encoding('<3', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('Female:', 'UTF-16BE', 'UTF-8'), mb_convert_encoding('Male:', 'UTF-16BE', 'UTF-8'));

    Permission::firstOrCreate(['name' => 'beneficiaries.view', 'guard_name' => 'web'], ['category' => 'beneficiaries', 'uuid' => (string) Str::uuid()]);
    $user->givePermissionTo('beneficiaries.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(app(BreakdownService::class)->forIndicator($all, $user, 'gender'))->toBe(['male' => 2.0, 'female' => 3.0]);
    $this->get($show)->assertOk()->assertViewHas('progress', fn (array $progress): bool => $progress['current'] === 2.0);
    expect($this->get(route('results-framework.report'))->assertOk()->streamedContent())->toContain('Male: 2', 'Female: 3');
});

test('indicator CSV neutralises formula text while keeping signed numeric cells', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $indicator = app(IndicatorService::class)->create($tenant->id, '=1+1', 5, today(), today(), ['baseline' => -2]);
    $indicator->update(['unit' => " \t@SUM(1)"]);
    $csv = $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('results-framework.report'))->assertOk()->streamedContent();
    $row = str_getcsv(explode("\n", $csv)[2], ',', '"', '');
    expect([$row[0], $row[2], $row[3]])->toBe(["'=1+1", "' \t@SUM(1)", '-2']);
});
