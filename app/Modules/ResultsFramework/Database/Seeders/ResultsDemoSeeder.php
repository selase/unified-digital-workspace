<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Database\Seeders;

use App\Models\Tenant;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\ResultChainService;
use App\Modules\ResultsFramework\Services\TargetService;
use App\Modules\ResultsFramework\Services\VarianceService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/** A few indicators in different states of progress. Runs once per tenant and never in production. Pick the tenant with DEMO_TENANT. */
final class ResultsDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Children given a school meal';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('ResultsDemoSeeder: refusing to write demo indicators into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('ResultsDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        if (ResultIndicator::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('ResultsDemoSeeder: demo indicators already present, skipping.');

            return;
        }

        (new ResultIndicator)->getConnection()->transaction(fn () => $this->seedResults($tenantId));
        $this->command?->info('ResultsDemoSeeder: 5 indicators created.');
    }

    private function seedResults(string $tenantId): void
    {
        $service = app(IndicatorService::class);
        $start = today()->subMonthsNoOverflow(6)->startOfMonth();
        $end = today()->addMonthsNoOverflow(6)->endOfMonth();
        $mealResult = $this->demoResult($tenantId, 'School feeding', ['Improve school attendance', 'Fewer pupils miss meals', 'Meals provided', 'Prepare and serve meals']);
        $waterResult = $this->demoResult($tenantId, 'Clean water', ['Improve access to clean water', 'Households use safe water', 'Boreholes commissioned', 'Drill and inspect boreholes']);

        $meals = $service->create($tenantId, self::MARKER, 1200, $start, $end, [
            'programme_id' => $mealResult->programme_id, 'result_id' => $mealResult->id, 'unit' => 'children',
            'definition' => 'Children receiving school meals.', 'means_of_verification' => 'School meal register',
        ]);
        foreach ([[90, 150], [60, 380], [30, 640]] as [$daysAgo, $value]) {
            $service->record($meals, $value, today()->subDays($daysAgo), null, null);
        }
        $service->record($meals, 820, today()->subDay(), null, null, [
            'gender' => [['bucket' => 'female', 'value' => 420], ['bucket' => 'male', 'value' => 400]],
        ]);

        $wells = $service->create($tenantId, 'Demo: Boreholes commissioned', 12, $start, today()->subDay(), [
            'programme_id' => $waterResult->programme_id, 'result_id' => $waterResult->id, 'unit' => 'boreholes',
        ]);
        $service->record($wells, 3, today()->subDays(45), 'Demo: First sites commissioned', null);
        app(VarianceService::class)->add($wells, 'Demo: Drilling was delayed at two sites.', 'Confirm the revised contractor schedule.', null);
        app(TargetService::class)->generate($meals, 'quarterly');

        foreach ([
            ['Demo: People reached with services', 500, 'people', 'beneficiaries.people_reached', $waterResult],
            ['Demo: Volunteer hours given', 800, 'hours', 'volunteers.hours_approved', $mealResult],
            ['Demo: Donations raised', 250000, 'GHS', 'donations.amount_raised', $waterResult],
        ] as [$name, $target, $unit, $measure, $result]) {
            $service->create($tenantId, $name, $target, $start, $end, [
                'programme_id' => $result->programme_id, 'result_id' => $result->id, 'unit' => $unit, 'measure_key' => $measure,
            ], allowDisabledMeasure: true);
        }
    }

    /** @param list<string> $titles */
    private function demoResult(string $tenantId, string $name, array $titles): ResultNode
    {
        $programme = ResultProgramme::query()->firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Demo: '.$name]);
        $chain = app(ResultChainService::class);
        $parent = null;
        foreach (array_keys(ResultNode::LEVELS) as $index => $level) {
            $parent = $chain->add($programme, $level, 'Demo: '.$titles[$index], $parent);
        }

        return $parent;
    }
}
