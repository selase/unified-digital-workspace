<?php

declare(strict_types=1);

namespace App\Modules\MonitoringEvaluation\Database\Seeders;

use App\Models\Tenant;
use App\Modules\MonitoringEvaluation\Models\MelIndicator;
use App\Modules\MonitoringEvaluation\Services\IndicatorService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Database\Seeder;

/** A few indicators in different states of progress. Runs once per tenant and never in production. Pick the tenant with DEMO_TENANT. */
final class MonitoringDemoSeeder extends Seeder
{
    private const MARKER = 'Demo: Children given a school meal';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('MonitoringDemoSeeder: refusing to write demo indicators into production.');

            return;
        }

        $tenant = Tenant::query()->where('slug', (string) config('app.demo_tenant'))->first();
        if (! $tenant instanceof Tenant) {
            $this->command?->warn('MonitoringDemoSeeder: tenant not found, skipping.');

            return;
        }

        app(TenantContext::class)->setTenant($tenant);
        setPermissionsTeamId($tenant->id);
        $databases = app(TenantDatabaseManager::class);
        $tenant->requiresDedicatedDb() ? $databases->configure($tenant) : $databases->configureShared();

        $tenantId = (string) $tenant->id;
        if (MelIndicator::query()->where('tenant_id', $tenantId)->where('name', self::MARKER)->exists()) {
            $this->command?->info('MonitoringDemoSeeder: demo indicators already present, skipping.');

            return;
        }

        $service = app(IndicatorService::class);
        $start = today()->startOfYear();
        $end = today()->endOfYear();

        $meals = $service->create($tenantId, self::MARKER, 1200, $start, $end, ['programme' => 'School feeding', 'unit' => 'children', 'baseline' => 0, 'description' => 'Average children fed per term.']);
        foreach ([[90, 150], [60, 380], [30, 640], [1, 820]] as [$daysAgo, $value]) {
            $service->record($meals, $value, today()->subDays($daysAgo), null, null);
        }

        $wells = $service->create($tenantId, 'Demo: Boreholes commissioned', 12, $start, $end, ['programme' => 'Clean water', 'unit' => 'boreholes']);
        $service->record($wells, 3, today()->subDays(45), 'Adaklu and Tafi Atome', null);

        $reached = $service->create($tenantId, 'Demo: People reached with services', 500, $start, $end, ['programme' => 'All programmes', 'unit' => 'people', 'source' => 'beneficiaries_reached']);
        $service->create($tenantId, 'Demo: Volunteer hours given', 800, $start, $end, ['programme' => 'Volunteering', 'unit' => 'hours', 'source' => 'volunteer_hours']);
        $service->create($tenantId, 'Demo: Donations raised', 250000, $start, $end, ['programme' => 'Fundraising', 'unit' => 'GHS', 'source' => 'donations_total']);
        unset($reached);

        $this->command?->info('MonitoringDemoSeeder: 5 indicators created.');
    }
}
