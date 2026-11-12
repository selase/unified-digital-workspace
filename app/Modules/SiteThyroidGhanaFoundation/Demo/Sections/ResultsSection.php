<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\ResultChainService;
use App\Modules\ResultsFramework\Services\TargetService;
use App\Modules\ResultsFramework\Services\VarianceService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoManifest;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/** Indicators with readings, plus three whose figures are calculated from the other modules. */
final class ResultsSection implements DemoSection
{
    public function label(): string
    {
        return 'Results Framework';
    }

    public function seed(Tenant $tenant): void
    {
        (new ResultIndicator)->getConnection()->transaction(fn () => $this->seedResults($tenant));
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        $indicatorIds = $remover->demoIds('rf_indicators', $tenant, $since, 'name');
        $readingIds = $remover->idsBelongingTo('rf_readings', 'indicator_id', $indicatorIds);
        $removed = $remover->deleteWhereIn('rf_reading_breakdowns', 'reading_id', $readingIds)
            + $remover->children('rf_variance_notes', 'indicator_id', 'rf_indicators', $tenant, $since, 'name')
            + $remover->children('rf_targets', 'indicator_id', 'rf_indicators', $tenant, $since, 'name')
            + $remover->children('rf_readings', 'indicator_id', 'rf_indicators', $tenant, $since, 'name')
            + $remover->rows('rf_indicators', $tenant, $since, 'name');

        if ($tenant->slug !== 'thyroid-ghana-foundation') {
            return $removed;
        }

        $manifest = app(DemoManifest::class);
        $nodes = ResultNode::query()->where('tenant_id', $tenant->id)->whereKey($manifest->resultNodeIds($tenant))
            ->where('created_at', '>=', $since)->where('title', 'like', 'Demo: %');
        foreach (array_reverse(array_keys(ResultNode::LEVELS)) as $level) {
            $removed += (clone $nodes)->where('level', $level)->whereDoesntHave('children')->whereDoesntHave('indicators')->delete();
        }

        $ids = $manifest->resultProgrammeIds($tenant);

        return $removed + ResultProgramme::query()->where('tenant_id', $tenant->id)->whereKey($ids)
            ->where('created_at', '>=', $since)->where('name', 'like', 'Demo: %')
            ->whereDoesntHave('indicators')
            ->whereNotIn('id', ResultNode::query()->where('tenant_id', $tenant->id)->select('programme_id'))
            ->delete();
    }

    private function seedResults(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $service = app(IndicatorService::class);
        $start = today()->subMonthsNoOverflow(6)->startOfMonth();
        $end = today()->addMonthsNoOverflow(6)->endOfMonth();
        $screening = $this->demoResult($tenant, 'Screening camps', ['Earlier thyroid diagnosis', 'Patients receive timely screening', 'Screening camps delivered', 'Hold community screening']);
        $surgery = $this->demoResult($tenant, 'Subsidised surgery', ['Improve access to treatment', 'Patients receive surgical care', 'Surgery subsidies provided', 'Coordinate surgical referrals']);

        $screened = $service->create($tenantId, 'Demo: People screened for thyroid disease', 2000, $start, $end, [
            'programme_id' => $screening->programme_id, 'result_id' => $screening->id, 'unit' => 'people',
            'definition' => 'People tested at screening camps.', 'means_of_verification' => 'Screening camp register',
        ]);
        foreach ([[90, 250], [60, 610], [30, 980]] as [$daysAgo, $value]) {
            $service->record($screened, $value, today()->subDays($daysAgo), null, null);
        }
        $service->record($screened, 1320, today()->subDay(), null, null, [
            'gender' => [['bucket' => 'female', 'value' => 800], ['bucket' => 'male', 'value' => 520]],
        ]);

        $surgeries = $service->create($tenantId, 'Demo: Subsidised surgeries completed', 40, $start, today()->subDay(), [
            'programme_id' => $surgery->programme_id, 'result_id' => $surgery->id, 'unit' => 'operations',
        ]);
        $service->record($surgeries, 9, today()->subDays(45), 'Demo: Phase 2, first cohort', null);
        app(VarianceService::class)->add($surgeries, 'Demo: Theatre availability delayed scheduled cases.', 'Agree new dates with the hospital.', null);
        app(TargetService::class)->generate($screened, 'quarterly');

        foreach ([
            ['Demo: People reached with services', 500, 'people', 'beneficiaries.people_reached', $screening],
            ['Demo: Volunteer hours given', 800, 'hours', 'volunteers.hours_approved', $screening],
            ['Demo: Donations raised', 250000, 'GHS', 'donations.amount_raised', $surgery],
        ] as [$name, $target, $unit, $measure, $result]) {
            $service->create($tenantId, $name, $target, $start, $end, [
                'programme_id' => $result->programme_id, 'result_id' => $result->id, 'unit' => $unit, 'measure_key' => $measure,
            ], allowDisabledMeasure: true);
        }
    }

    /** @param list<string> $titles */
    private function demoResult(Tenant $tenant, string $name, array $titles): ResultNode
    {
        $manifest = app(DemoManifest::class);
        $programme = ResultProgramme::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Demo: '.$name]);
        if ($programme->wasRecentlyCreated) {
            $manifest->rememberResultProgrammeId($tenant, $programme->id);
        }

        $chain = app(ResultChainService::class);
        $parent = null;
        $ids = [];
        foreach (array_keys(ResultNode::LEVELS) as $index => $level) {
            $parent = $chain->add($programme, $level, 'Demo: '.$titles[$index], $parent);
            $ids[] = $parent->id;
        }
        $manifest->rememberResultNodeIds($tenant, $ids);

        return $parent;
    }
}
