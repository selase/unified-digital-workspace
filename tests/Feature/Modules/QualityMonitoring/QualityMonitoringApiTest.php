<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\KpiUpdate;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Variance;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createQualityApiContext(): array
{
    $tenantDb = database_path('tenant_quality_testing.sqlite');
    if (file_exists($tenantDb)) {
        unlink($tenantDb);
    }
    touch($tenantDb);

    Config::set('database.connections.tenant', [
        'driver' => 'sqlite',
        'database' => $tenantDb,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Config::set('database.default_tenant_connection', 'tenant');
    DB::purge('tenant');
    DB::reconnect('tenant');

    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user, [
        'isolation_mode' => 'db_per_tenant',
        'db_driver' => 'sqlite',
        'meta' => [
            'database' => $tenantDb,
        ],
    ]);

    foreach (['qm.workplans.view', 'qm.workplans.manage', 'qm.approvals.manage', 'qm.variances.manage', 'qm.alerts.manage', 'qm.kpis.manage'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'quality',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo(['qm.workplans.view', 'qm.workplans.manage', 'qm.approvals.manage', 'qm.variances.manage', 'qm.alerts.manage', 'qm.kpis.manage']);

    app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

    Artisan::call('migrate', [
        '--database' => 'tenant',
        '--path' => app_path('Modules/QualityMonitoring/Database/Migrations'),
        '--realpath' => true,
    ]);

    return [$user, $tenant];
}

it('creates and updates workplans', function (): void {
    [$user] = createQualityApiContext();

    $create = actingAs($user, 'sanctum')->postJson('/api/quality-monitoring/v1/workplans', [
        'title' => 'Q1 Workplan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'draft',
    ]);

    $create->assertCreated();

    $workplanId = $create->json('id');

    $update = actingAs($user, 'sanctum')->putJson("/api/quality-monitoring/v1/workplans/{$workplanId}", [
        'status' => 'submitted',
    ]);

    $update->assertSuccessful()->assertJsonFragment([
        'status' => 'submitted',
    ]);
});

it('submits and approves workplans', function (): void {
    [$user] = createQualityApiContext();

    $create = actingAs($user, 'sanctum')->postJson('/api/quality-monitoring/v1/workplans', [
        'title' => 'Q2 Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'draft',
    ]);

    $create->assertCreated();

    $workplanId = $create->json('id');

    actingAs($user, 'sanctum')
        ->postJson("/api/quality-monitoring/v1/workplans/{$workplanId}/submit", [
            'notes' => 'Ready for review',
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'status' => 'submitted',
        ]);

    actingAs($user, 'sanctum')
        ->postJson("/api/quality-monitoring/v1/workplans/{$workplanId}/approve", [
            'comments' => 'Approved',
        ])
        ->assertSuccessful()
        ->assertJsonFragment([
            'status' => 'approved',
        ]);
});

it('lists workplans with filters', function (): void {
    [$user] = createQualityApiContext();

    Workplan::create([
        'title' => 'Annual Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addYear()->toDateString(),
        'status' => 'draft',
    ]);

    $response = actingAs($user, 'sanctum')->getJson('/api/quality-monitoring/v1/workplans?status=draft');

    $response->assertSuccessful()->assertJsonFragment([
        'title' => 'Annual Plan',
    ]);
});

it('returns workplan dashboard stats', function (): void {
    [$user] = createQualityApiContext();

    $workplan = Workplan::create([
        'title' => 'Dashboard Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'draft',
    ]);

    $objective = Objective::create([
        'workplan_id' => $workplan->id,
        'title' => 'Objective',
    ]);

    $activity = Activity::create([
        'objective_id' => $objective->id,
        'title' => 'Activity',
    ]);

    $kpi = Kpi::create([
        'activity_id' => $activity->id,
        'name' => 'KPI',
    ]);

    KpiUpdate::create([
        'kpi_id' => $kpi->id,
        'value' => 10,
    ]);

    Variance::create([
        'workplan_id' => $workplan->id,
        'activity_id' => $activity->id,
        'category' => 'delay',
        'narrative' => 'Delay reason',
    ]);

    Alert::create([
        'workplan_id' => $workplan->id,
        'type' => 'overdue',
        'status' => 'open',
    ]);

    $response = actingAs($user, 'sanctum')->getJson("/api/quality-monitoring/v1/workplans/{$workplan->id}/dashboard");

    $response->assertSuccessful()->assertJsonFragment([
        'workplan_id' => $workplan->id,
        'objectives' => 1,
        'activities' => 1,
        'kpis' => 1,
        'kpi_updates' => 1,
        'variances' => 1,
        'alerts' => 1,
    ]);
});

it('returns workplan report summary and exports alerts', function (): void {
    [$user] = createQualityApiContext();

    $workplan = Workplan::create([
        'title' => 'Reporting Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(3)->toDateString(),
        'status' => 'draft',
        'owner_id' => $user->id,
    ]);

    $objective = Objective::create([
        'workplan_id' => $workplan->id,
        'title' => 'Objective',
    ]);

    $activity = Activity::create([
        'objective_id' => $objective->id,
        'title' => 'Activity',
        'status' => 'in-progress',
    ]);

    $kpi = Kpi::create([
        'activity_id' => $activity->id,
        'name' => 'KPI',
    ]);

    KpiUpdate::create([
        'kpi_id' => $kpi->id,
        'value' => 12,
    ]);

    Variance::create([
        'workplan_id' => $workplan->id,
        'activity_id' => $activity->id,
        'category' => 'delay',
        'narrative' => 'Reason',
    ]);

    Alert::create([
        'workplan_id' => $workplan->id,
        'type' => 'overdue',
        'status' => 'open',
        'escalation_level' => 1,
    ]);

    $summary = actingAs($user, 'sanctum')->getJson("/api/quality-monitoring/v1/workplans/{$workplan->id}/reports/summary");

    $summary->assertSuccessful()->assertJsonPath('workplan_id', $workplan->id);
    $summary->assertJsonPath('alerts.escalated', 1);
    $summary->assertJsonPath('activity_status.in-progress', 1);

    $export = actingAs($user, 'sanctum')->get("/api/quality-monitoring/v1/workplans/{$workplan->id}/exports/alerts?format=csv");

    $export->assertSuccessful();
    $export->assertHeader('content-disposition', "attachment; filename=workplan-alerts-{$workplan->id}.csv");
});

it('stores variance and acknowledges alerts', function (): void {
    [$user] = createQualityApiContext();

    $workplan = Workplan::create([
        'title' => 'Variance Plan',
        'period_start' => now()->toDateString(),
        'period_end' => now()->addMonths(6)->toDateString(),
        'status' => 'approved',
    ]);

    $objective = Objective::create([
        'workplan_id' => $workplan->id,
        'title' => 'Objective',
    ]);

    $activity = Activity::create([
        'objective_id' => $objective->id,
        'title' => 'Activity',
    ]);

    $variance = actingAs($user, 'sanctum')->postJson("/api/quality-monitoring/v1/activities/{$activity->id}/variances", [
        'category' => 'delay',
        'narrative' => 'Late due to dependency',
    ]);

    $variance->assertCreated();

    $alert = Alert::create([
        'workplan_id' => $workplan->id,
        'type' => 'overdue',
        'status' => 'open',
    ]);

    $ack = actingAs($user, 'sanctum')->postJson("/api/quality-monitoring/v1/alerts/{$alert->id}/ack", [
        'notes' => 'Noted',
    ]);

    $ack->assertSuccessful()->assertJsonFragment([
        'status' => 'acknowledged',
    ]);
});
