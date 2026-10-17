<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Variance;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createQualityWebContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'qm.workplans.view',
        'qm.workplans.manage',
        'qm.approvals.manage',
        'qm.kpis.manage',
        'qm.reviews.manage',
        'qm.alerts.manage',
        'qm.variances.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'quality-monitoring',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

    return [$user, $tenant];
}

it('renders quality monitoring hub and workplans register with metronic components', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Infection Prevention & Control Strategic Plan 2026',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'active',
        'owner_id' => (string) $user->uuid,
        'org_scope' => ['unit' => 'Clinical Governance'],
    ]);

    $hubResponse = test()->actingAs($user)->get(route('quality-monitoring.index'));
    $hubResponse->assertOk()
        ->assertSee('Quality Monitoring & Accreditation Hub')
        ->assertSee('Infection Prevention & Control Strategic Plan 2026')
        ->assertSee('Active Workplans');

    $regResponse = test()->actingAs($user)->get(route('quality-monitoring.workplans.index'));
    $regResponse->assertOk()
        ->assertSee('Quality & Performance Workplans')
        ->assertSee('Infection Prevention & Control Strategic Plan 2026')
        ->assertSee('Clinical Governance');
});

it('renders workplan creation form for authorized quality officers', function (): void {
    [$user] = createQualityWebContext();

    $response = test()->actingAs($user)->get(route('quality-monitoring.workplans.create'));
    $response->assertOk()
        ->assertSee('Initialize Quality Workplan')
        ->assertSee('Workplan Title')
        ->assertSee('Audit Period Start');
});

it('stores a new workplan with initial version snapshot and owner', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $response = test()->actingAs($user)->post(route('quality-monitoring.workplans.store'), [
        'title' => 'Surgical Safety Checklist Compliance Programme',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'owner_id' => (string) $user->uuid,
        'org_scope' => ['unit' => 'Perioperative Services'],
        'metadata' => [
            'summary' => 'Achieve 99% perioperative compliance with WHO surgical safety checklist timeouts.',
            'standard' => 'JCI International Patient Safety Goals (IPSG 4)',
        ],
    ]);

    $workplan = Workplan::query()->where('title', 'Surgical Safety Checklist Compliance Programme')->first();
    expect($workplan)->not->toBeNull();
    assert($workplan !== null);

    $response->assertRedirect(route('quality-monitoring.workplans.show', $workplan));

    expect($workplan->status)->toBe('draft')
        ->and($workplan->owner_id)->toBe((string) $user->uuid)
        ->and($workplan->versions()->count())->toBe(1);

    expect($workplan->versions()->first()->version_no)->toBe(1);
});

it('displays workplan detail workspace with objectives, activities, and KPIs', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Emergency Department Throughput & Clinical Triage Audit',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'draft',
        'owner_id' => (string) $user->uuid,
    ]);

    $objective = Objective::create([
        'tenant_id' => $tenant->id,
        'workplan_id' => $workplan->id,
        'title' => 'Reduce Door-to-Doctor Time for Category 2 Patients',
        'weight' => 40,
    ]);

    $activity = Activity::create([
        'tenant_id' => $tenant->id,
        'objective_id' => $objective->id,
        'title' => 'Deploy Rapid Physician Assessment Pod at Triage',
        'status' => 'in_progress',
        'responsible_id' => (string) $user->uuid,
    ]);

    $kpi = Kpi::create([
        'tenant_id' => $tenant->id,
        'activity_id' => $activity->id,
        'name' => 'Door-to-Doctor Time (Minutes)',
        'unit' => 'Minutes',
        'target_value' => 20.00,
        'baseline_value' => 38.00,
        'direction' => 'decrease',
    ]);

    $response = test()->actingAs($user)->get(route('quality-monitoring.workplans.show', $workplan));
    $response->assertOk()
        ->assertSee('Emergency Department Throughput & Clinical Triage Audit')
        ->assertSee('Reduce Door-to-Doctor Time for Category 2 Patients')
        ->assertSee('Deploy Rapid Physician Assessment Pod at Triage')
        ->assertSee('Door-to-Doctor Time (Minutes)');
});

it('submits workplan for committee review and records version milestone', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Radiology Turnaround Time Optimization Plan',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'draft',
    ]);

    $submitResponse = test()->actingAs($user)->post(route('quality-monitoring.workplans.submit', $workplan));
    $submitResponse->assertRedirect(route('quality-monitoring.workplans.show', $workplan));

    $workplan->refresh();
    expect($workplan->status)->toBe('submitted');
    expect($workplan->versions()->where('status', 'submitted')->count())->toBe(1);
});

it('approves workplan and activates performance monitoring upon committee decision', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Inpatient Fall Prevention & Mobility Assessment Protocol',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'submitted',
    ]);

    $approveResponse = test()->actingAs($user)->post(route('quality-monitoring.workplans.approve', $workplan), [
        'comments' => 'Approved unconditionally by Hospital Quality & Patient Safety Committee.',
    ]);
    $approveResponse->assertRedirect(route('quality-monitoring.workplans.show', $workplan));

    $workplan->refresh();
    expect($workplan->status)->toBe('active');
    expect($workplan->reviews()->count())->toBe(1);
    expect($workplan->reviews()->first()->status)->toBe('approved');
});

it('rejects workplan and returns to draft with revision remarks', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Pharmacy Medication Reconciliation Audit',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'submitted',
    ]);

    $rejectResponse = test()->actingAs($user)->post(route('quality-monitoring.workplans.reject', $workplan), [
        'comments' => 'Please incorporate discharge reconciliation metrics before re-submitting.',
    ]);
    $rejectResponse->assertRedirect(route('quality-monitoring.workplans.show', $workplan));

    $workplan->refresh();
    expect($workplan->status)->toBe('draft');
    expect($workplan->reviews()->first()->status)->toBe('rejected');
});

it('records a KPI measurement update and generates threshold breach alert', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Hand Hygiene Hospital-Wide Observational Audit',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'active',
    ]);

    $objective = Objective::create([
        'tenant_id' => $tenant->id,
        'workplan_id' => $workplan->id,
        'title' => 'Achieve Minimum 95% Hand Hygiene Compliance Across Inpatient Wards',
    ]);

    $activity = Activity::create([
        'tenant_id' => $tenant->id,
        'objective_id' => $objective->id,
        'title' => 'Bi-weekly Secret Shopper Compliance Audits',
    ]);

    $kpi = Kpi::create([
        'tenant_id' => $tenant->id,
        'activity_id' => $activity->id,
        'name' => 'Hand Hygiene Compliance %',
        'unit' => '%',
        'target_value' => 95.00,
        'baseline_value' => 88.00,
        'direction' => 'increase',
    ]);

    // Record an update below target (82% < 95%)
    $updateResponse = test()->actingAs($user)->post(route('quality-monitoring.workplans.kpi-update', [$workplan, $kpi]), [
        'value' => 82.00,
        'note' => 'Q3 Surgical ICU audit round 1',
    ]);
    $updateResponse->assertRedirect(route('quality-monitoring.workplans.show', $workplan));

    expect($kpi->updates()->count())->toBe(1);
    expect((float) $kpi->updates()->first()->value)->toBe(82.00);

    // Assert threshold alert was triggered
    $alert = Alert::query()->where('workplan_id', $workplan->id)->where('kpi_id', $kpi->id)->first();
    expect($alert)->not->toBeNull();
    assert($alert !== null);
    expect($alert->type)->toBe('threshold_breach')
        ->and($alert->status)->toBe('open');
});

it('logs quality variance with corrective and preventive action plan', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Central Line-Associated Bloodstream Infection (CLABSI) Reduction',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'active',
    ]);

    $varianceResponse = test()->actingAs($user)->post(route('quality-monitoring.workplans.variance', $workplan), [
        'category' => 'Infection Control Threshold',
        'impact_level' => 'high',
        'narrative' => 'CLABSI rate in Adult Medical ICU increased from 0.8 to 2.4 per 1,000 line days during August.',
        'corrective_action' => 'Mandate full chlorhexidine skin prep re-certification for all ICU medical and nursing fellows; audit dressing change frequency daily.',
        'revised_date' => now()->addMonth()->toDateString(),
    ]);
    $varianceResponse->assertRedirect(route('quality-monitoring.workplans.show', $workplan));

    $variance = Variance::query()->where('workplan_id', $workplan->id)->first();
    expect($variance)->not->toBeNull();
    assert($variance !== null);
    expect($variance->category)->toBe('Infection Control Threshold')
        ->and($variance->impact_level)->toBe('high')
        ->and($variance->status)->toBe('pending');
});

it('acknowledges a quality performance alert', function (): void {
    [$user, $tenant] = createQualityWebContext();

    $workplan = Workplan::create([
        'tenant_id' => $tenant->id,
        'title' => 'Critical Lab Values Turnaround Time Audit',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'active',
    ]);

    $alert = Alert::create([
        'tenant_id' => $tenant->id,
        'workplan_id' => $workplan->id,
        'type' => 'critical_delay',
        'status' => 'open',
    ]);

    $ackResponse = test()->actingAs($user)->post(route('quality-monitoring.alerts.acknowledge', $alert));
    $ackResponse->assertRedirect();

    $alert->refresh();
    expect($alert->status)->toBe('acknowledged');
});
