<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Database\Seeders;

use App\Models\User;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\DataSource;
use App\Modules\QualityMonitoring\Models\Indicator;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\KpiUpdate;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Review;
use App\Modules\QualityMonitoring\Models\Variance;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Modules\QualityMonitoring\Models\WorkplanVersion;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class QualityMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        if (! $tenant) {
            return;
        }

        $tenantConnection = config('database.default_tenant_connection', 'tenant');

        if (! Schema::connection($tenantConnection)->hasTable('qm_workplans')) {
            return;
        }

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $owner) {
            return;
        }

        $workplan = Workplan::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'title' => '2026 Service Quality Workplan',
        ], [
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'owner_id' => (string) $owner->uuid,
            'org_scope' => [
                'tenant_wide' => true,
            ],
            'metadata' => [
                'seed' => true,
            ],
        ]);

        WorkplanVersion::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workplan_id' => $workplan->id,
            'version_no' => 1,
        ], [
            'status' => 'approved',
            'payload' => [
                'summary' => 'Initial approved baseline for service quality monitoring.',
            ],
            'submitted_at' => now()->subMonths(1),
            'approved_at' => now()->subWeeks(3),
            'created_by' => (string) $owner->uuid,
        ]);

        $objective = Objective::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workplan_id' => $workplan->id,
            'title' => 'Improve Incident Response Effectiveness',
        ], [
            'description' => 'Reduce response times and improve closure quality.',
            'weight' => 45,
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $activity = Activity::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'objective_id' => $objective->id,
            'title' => 'Weekly response time review',
        ], [
            'description' => 'Review incident queue and adherence to SLA targets.',
            'responsible_id' => (string) $owner->uuid,
            'start_date' => now()->subWeeks(4)->toDateString(),
            'due_date' => now()->addWeeks(8)->toDateString(),
            'status' => 'in-progress',
            'weight' => 20,
            'sort_order' => 1,
        ]);

        $indicator = Indicator::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Average First Response Time',
        ], [
            'type' => 'outcome',
            'unit' => 'minutes',
            'definition' => 'Average time from incident creation to first response.',
            'formula_notes' => 'Total first response minutes / number of incidents responded.',
            'metadata' => [
                'seed' => true,
            ],
        ]);

        DataSource::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Incident Management SLA Feed',
        ], [
            'description' => 'SLA metrics exported from incident management module.',
            'method' => 'api',
            'custodian' => 'Operations Team',
            'quality_notes' => 'Validated weekly before review meetings.',
            'metadata' => [
                'frequency' => 'weekly',
            ],
        ]);

        $kpi = Kpi::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'activity_id' => $activity->id,
            'name' => 'First Response Time SLA',
        ], [
            'indicator_id' => $indicator->id,
            'unit' => 'minutes',
            'target_value' => 30,
            'baseline_value' => 62,
            'direction' => 'decrease',
            'calculation' => [
                'window' => 'weekly',
            ],
            'frequency' => 'weekly',
        ]);

        KpiUpdate::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'kpi_id' => $kpi->id,
            'captured_at' => now()->subWeeks(1)->startOfDay(),
        ], [
            'value' => 41,
            'note' => 'Latency improved after queue balancing update.',
            'captured_by_id' => (string) $owner->uuid,
            'evidence_path' => 'quality/weekly-response-report.pdf',
            'evidence_mime' => 'application/pdf',
            'evidence_size' => 129_440,
        ]);

        Review::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workplan_id' => $workplan->id,
            'reviewer_id' => (string) $owner->uuid,
        ], [
            'status' => 'approved',
            'comments' => 'Trajectory is positive. Keep weekly escalation checks active.',
            'scores' => [
                'delivery' => 4,
                'quality' => 4,
                'timeliness' => 5,
            ],
            'submitted_at' => now()->subWeeks(2),
            'approved_at' => now()->subWeeks(2)->addDay(),
        ]);

        Alert::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workplan_id' => $workplan->id,
            'kpi_id' => $kpi->id,
            'type' => 'variance-warning',
        ], [
            'status' => 'open',
            'metadata' => [
                'threshold' => 30,
                'actual' => 41,
            ],
            'sent_at' => now()->subDays(3),
            'escalation_level' => 1,
            'escalated_at' => now()->subDays(2),
        ]);

        Variance::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'workplan_id' => $workplan->id,
            'kpi_id' => $kpi->id,
            'category' => 'schedule',
        ], [
            'activity_id' => $activity->id,
            'impact_level' => 'medium',
            'narrative' => 'Response time remains above target during late evening periods.',
            'corrective_action' => 'Introduce secondary on-call rotation for evening traffic.',
            'revised_date' => now()->addWeeks(2)->toDateString(),
            'status' => 'pending',
            'reviewed_by_id' => (string) $owner->uuid,
            'reviewed_at' => now()->subDay(),
        ]);
    }
}
