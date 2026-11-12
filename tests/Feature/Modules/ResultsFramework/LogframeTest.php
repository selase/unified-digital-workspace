<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Modules\Beneficiaries\Models\BenBeneficiary;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\LogframeReport;
use App\Modules\ResultsFramework\Services\ProgrammeService;
use App\Modules\ResultsFramework\Services\ResultChainService;

require_once __DIR__.'/fixtures.php';

test('the logframe follows the programme tree and exports its matrix in the agreed column order', function (): void {
    [$user, $tenant] = createMonitoringContext();
    $programme = app(ProgrammeService::class)->create($tenant->id, 'Water');
    $chain = app(ResultChainService::class);
    $goal = $chain->add($programme, 'goal', 'Clean water');
    $outcome = $chain->add($programme, 'outcome', 'Safer water', $goal);
    $output = $chain->add($programme, 'output', 'Wells built', $outcome, ['assumptions' => '@Funding', 'risks' => '-Drought']);
    $chain->add($programme, 'activity', 'Dig wells', $output);
    $chain->add($programme, 'output', 'Maintain wells', $outcome, ['sort_order' => 1]);
    $service = app(IndicatorService::class);
    $indicator = $service->create($tenant->id, '=1+1', 4, today()->subMonth(), today()->addMonth(), [
        'programme_id' => $programme->id, 'result_id' => $output->id, 'baseline' => -2, 'means_of_verification' => 'Site register',
    ]);
    $service->record($indicator, 4, today(), null, null);
    $service->create($tenant->id, 'Unlinked indicator', 5, today(), today(), ['programme_id' => $programme->id]);

    $rows = app(LogframeReport::class)->rows($programme, $user);
    expect(array_column($rows, 'title'))->toBe(['Clean water', 'Safer water', 'Wells built', 'Dig wells', 'Maintain wells'])
        ->and(array_column($rows, 'depth'))->toBe([0, 1, 2, 3, 2])
        ->and($rows[3]->indicators)->toBe([]);

    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $url = route('results-framework.programmes.logframe', $programme->uuid);
    $this->get(route('results-framework.programmes.show', $programme->uuid))->assertOk()->assertSee($url);
    $this->get($url)->assertOk()->assertSeeInOrder(['Clean water', 'Safer water', 'Wells built', 'Site register', 'Dig wells'])->assertSee('1 programme indicator is omitted because it has no linked result.')->assertDontSee('Unlinked indicator');
    $csv = $this->get($url.'?format=csv')->assertOk()->assertDownload()->streamedContent();
    $matrix = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), explode("\n", mb_rtrim($csv, "\n")));
    expect($matrix[0])->toBe(['Level', 'Result', 'Indicator', 'Baseline', 'Target', 'Current', 'Status', 'Means of verification', 'Assumptions', 'Risks'])
        ->and($matrix[3])->toBe(['Output', 'Wells built', "'=1+1", '-2', '4', '4', 'Achieved', 'Site register', "'@Funding", "'-Drought"])
        ->and($matrix[4])->toBe(['Activity', 'Dig wells', '', '', '', '', '', '', '', ''])
        ->and($csv)->toContain('1 programme indicator is omitted because it has no linked result.')->not->toContain('Unlinked indicator');
    $pdf = $this->get($url.'?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');
});

test('the logframe protects confidential figures and checks tenant scope for exports', function (): void {
    [$user, $tenant] = createMonitoringContext();
    enableResultMeasureModules($tenant, 'beneficiaries');
    $programmes = app(ProgrammeService::class);
    $programme = $programmes->create($tenant->id, 'Reach');
    $other = $programmes->create(Tenant::factory()->create()->id, 'Other programme');
    $result = app(ResultChainService::class)->add($programme, 'goal', 'Help people');
    $person = BenBeneficiary::query()->create(['tenant_id' => $tenant->id, 'full_name' => 'Test person', 'gender' => 'female']);
    $person->services()->create(['tenant_id' => $tenant->id, 'type' => 'food', 'delivered_on' => today()]);
    app(IndicatorService::class)->create($tenant->id, 'People helped', 10, today(), today(), [
        'programme_id' => $programme->id, 'result_id' => $result->id, 'measure_key' => 'beneficiaries.people_reached',
    ]);
    $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id]);
    $url = route('results-framework.programmes.logframe', $programme->uuid);
    $this->get($url)->assertOk()->assertSee('<3')->assertSee('Hidden to protect small groups')->assertDontSee('Female');
    $csv = $this->get($url.'?format=csv')->assertOk()->streamedContent();
    expect($csv)->toContain('<3', 'Restricted', 'Breakdown details are available in the web and PDF logframe.')->not->toContain('Female');
    $pdf = $this->get($url.'?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    preg_match_all('/\bstream\r?\n(.*?)\r?\nendstream\b/s', $pdf->getContent(), $matches);
    $streams = collect($matches[1])->map(fn (string $stream): string => @gzuncompress($stream) ?: $stream)->implode('');
    expect($streams)->toContain(mb_convert_encoding('<3', 'UTF-16BE', 'UTF-8'))
        ->not->toContain(mb_convert_encoding('Female', 'UTF-16BE', 'UTF-8'));
    $this->get(route('results-framework.programmes.logframe', [$other->uuid, 'format' => 'csv']))->assertNotFound();
    $user->revokePermissionTo('results.view');
    $this->get($url.'?format=pdf')->assertForbidden();
});
