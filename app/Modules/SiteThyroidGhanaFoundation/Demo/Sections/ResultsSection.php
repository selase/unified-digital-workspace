<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\IndicatorService;
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
        $tenantId = (string) $tenant->id;
        $service = app(IndicatorService::class);
        $start = today()->startOfYear();
        $end = today()->endOfYear();

        $screened = $service->create($tenantId, 'Demo: People screened for thyroid disease', 2000, $start, $end, ['programme_id' => $this->demoProgramme($tenant, 'Screening camps'), 'unit' => 'people', 'baseline' => 0, 'description' => 'People tested at screening camps this year.']);
        foreach ([[90, 250], [60, 610], [30, 980], [1, 1320]] as [$daysAgo, $value]) {
            $service->record($screened, $value, today()->subDays($daysAgo), null, null);
        }

        $surgeries = $service->create($tenantId, 'Demo: Subsidised surgeries completed', 40, $start, $end, ['programme_id' => $this->demoProgramme($tenant, 'Subsidised surgery'), 'unit' => 'operations']);
        $service->record($surgeries, 9, today()->subDays(45), 'Phase 2, first cohort', null);

        $service->create($tenantId, 'Demo: People reached with services', 500, $start, $end, ['programme_id' => $this->demoProgramme($tenant, 'All programmes'), 'unit' => 'people', 'measure_key' => 'beneficiaries.people_reached'], allowDisabledMeasure: true);
        $service->create($tenantId, 'Demo: Volunteer hours given', 800, $start, $end, ['programme_id' => $this->demoProgramme($tenant, 'Volunteering'), 'unit' => 'hours', 'measure_key' => 'volunteers.hours_approved'], allowDisabledMeasure: true);
        $service->create($tenantId, 'Demo: Donations raised', 250000, $start, $end, ['programme_id' => $this->demoProgramme($tenant, 'Fundraising'), 'unit' => 'GHS', 'measure_key' => 'donations.amount_raised'], allowDisabledMeasure: true);
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        $removed = $remover->children('rf_readings', 'indicator_id', 'rf_indicators', $tenant, $since, 'name') + $remover->rows('rf_indicators', $tenant, $since, 'name');

        if ($tenant->slug !== 'thyroid-ghana-foundation') {
            return $removed;
        }

        $ids = app(DemoManifest::class)->resultProgrammeIds($tenant);

        return $removed + ResultProgramme::query()->where('tenant_id', $tenant->id)->whereKey($ids)
            ->where('created_at', '>=', $since)->where('name', 'like', 'Demo: %')
            ->whereDoesntHave('indicators')
            ->whereNotIn('id', ResultNode::query()->where('tenant_id', $tenant->id)->select('programme_id'))
            ->delete();
    }

    private function demoProgramme(Tenant $tenant, string $name): int
    {
        $programme = ResultProgramme::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Demo: '.$name]);

        if ($programme->wasRecentlyCreated) {
            app(DemoManifest::class)->rememberResultProgrammeId($tenant, $programme->id);
        }

        return $programme->id;
    }
}
