<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Demo\Sections;

use App\Models\Tenant;
use App\Modules\ProgrammeResults\Services\IndicatorService;
use App\Modules\SiteThyroidGhanaFoundation\Demo\DemoSection;
use App\Modules\SiteThyroidGhanaFoundation\Demo\RowRemover;
use Carbon\CarbonImmutable;

/** Indicators with readings, plus three whose figures are calculated from the other modules. */
final class ResultsSection implements DemoSection
{
    public function label(): string
    {
        return 'NGO M&E';
    }

    public function seed(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->id;
        $service = app(IndicatorService::class);
        $start = today()->startOfYear();
        $end = today()->endOfYear();

        $screened = $service->create($tenantId, 'Demo: People screened for thyroid disease', 2000, $start, $end, ['programme' => 'Screening camps', 'unit' => 'people', 'baseline' => 0, 'description' => 'People tested at screening camps this year.']);
        foreach ([[90, 250], [60, 610], [30, 980], [1, 1320]] as [$daysAgo, $value]) {
            $service->record($screened, $value, today()->subDays($daysAgo), null, null);
        }

        $surgeries = $service->create($tenantId, 'Demo: Subsidised surgeries completed', 40, $start, $end, ['programme' => 'Subsidised surgery', 'unit' => 'operations']);
        $service->record($surgeries, 9, today()->subDays(45), 'Phase 2, first cohort', null);

        $service->create($tenantId, 'Demo: People reached with services', 500, $start, $end, ['programme' => 'All programmes', 'unit' => 'people', 'source' => 'beneficiaries_reached']);
        $service->create($tenantId, 'Demo: Volunteer hours given', 800, $start, $end, ['programme' => 'Volunteering', 'unit' => 'hours', 'source' => 'volunteer_hours']);
        $service->create($tenantId, 'Demo: Donations raised', 250000, $start, $end, ['programme' => 'Fundraising', 'unit' => 'GHS', 'source' => 'donations_total']);
    }

    public function remove(Tenant $tenant, CarbonImmutable $since): int
    {
        $remover = new RowRemover;

        return $remover->rows('pr_readings', $tenant, $since) + $remover->rows('pr_indicators', $tenant, $since);
    }
}
