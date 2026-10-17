<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Leave\LeaveCategory;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Workplan;
use App\Notifications\IncidentEscalatedNotification;
use App\Notifications\LeaveApprovalRequiredNotification;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

describe('P1-2: Notifications & Temporal State Invariants Test', function () {
    beforeEach(function () {
        foreach ([
            'hrms.leave.request',
            'hrms.leave.view',
            'hrms.leave.manage',
            'incidents.view',
            'incidents.manage',
            'incidents.escalate',
            'qm.workplans.view',
            'qm.workplans.manage',
            'qm.kpis.manage',
        ] as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web'],
                ['uuid' => (string) Str::uuid(), 'category' => 'general']
            );
        }
    });

    test('Leave submission dispatches LeaveApprovalRequiredNotification', function () {
        Notification::fake();

        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('hrms-core', $tenant);

        $user->givePermissionTo(['hrms.leave.request', 'hrms.leave.view']);

        $supervisorUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($supervisorUser->id);

        $supervisorEmp = Employee::create([
            'tenant_id' => $tenant->id,
            'user_id' => $supervisorUser->id,
            'first_name' => 'Kofi',
            'last_name' => 'Supervisor',
            'email' => 'kofi.supervisor@example.test',
            'is_active' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'Clinical Pathology',
            'slug' => 'pathology-'.Str::random(4),
            'head_employee_id' => $supervisorEmp->id,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'first_name' => 'Ama',
            'last_name' => 'Applicant',
            'email' => 'ama.applicant@example.test',
            'is_active' => true,
        ]);

        $employee->departments()->attach($department->id);

        $leaveCategory = LeaveCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Annual Leave',
            'default_days' => 30,
            'is_active' => true,
        ]);

        $startDate = Carbon::now()->addDays(2)->toDateString();
        $endDate = Carbon::now()->addDays(6)->toDateString();

        $response = $this->actingAs($user)->post(route('hrms-core.leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_category_id' => $leaveCategory->id,
            'proposed_start_date' => $startDate,
            'proposed_end_date' => $endDate,
            'leave_reasons' => 'Family holiday',
        ]);

        $response->assertSessionHas('status', 'success');

        Notification::assertSentTo(
            $supervisorUser,
            LeaveApprovalRequiredNotification::class
        );
    });

    test('Incident escalation dispatches IncidentEscalatedNotification', function () {
        Notification::fake();

        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('incident-management', $tenant);

        $user->givePermissionTo(['incidents.view', 'incidents.manage', 'incidents.escalate']);

        $escalatedToUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $tenant->users()->attach($escalatedToUser->id);

        $category = IncidentCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Infrastructure Safety',
            'slug' => 'infrastructure-safety-'.Str::random(4),
        ]);
        $priority = IncidentPriority::create([
            'tenant_id' => $tenant->id,
            'name' => 'High',
            'slug' => 'high-'.Str::random(4),
            'level' => 2,
        ]);
        $status = IncidentStatus::create([
            'tenant_id' => $tenant->id,
            'name' => 'Open',
            'slug' => 'open-'.Str::random(4),
        ]);

        $incident = Incident::create([
            'tenant_id' => $tenant->id,
            'reference_code' => 'INC-'.Str::random(6),
            'title' => 'Operating Theatre Power Surge',
            'description' => 'Backup generator failed to kick in immediately',
            'category_id' => $category->id,
            'priority_id' => $priority->id,
            'status_id' => $status->id,
            'reported_by_id' => (string) $user->uuid,
            'assigned_to_id' => (string) $escalatedToUser->uuid,
        ]);

        $criticalPriority = IncidentPriority::create([
            'tenant_id' => $tenant->id,
            'name' => 'Critical',
            'slug' => 'critical-'.Str::random(4),
            'level' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('incident-management.incidents.escalate', $incident), [
            'reason' => 'Critical patient safety risk',
            'to_priority_id' => $criticalPriority->id,
        ]);

        $response->assertSessionHas('status', 'success');

        Notification::assertSentTo(
            $escalatedToUser,
            IncidentEscalatedNotification::class
        );
    });

    test('Quality Monitoring threshold breach creates Alert with sent_at null', function () {
        $user = User::factory()->create();
        $tenant = setActiveTenantForTest($user);
        Session::put('active_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(TenantContext::class)->setTenant($tenant);
        app(ModuleManager::class)->enableForTenant('quality-monitoring', $tenant);

        $user->givePermissionTo(['qm.workplans.view', 'qm.workplans.manage', 'qm.kpis.manage']);

        $workplan = Workplan::create([
            'tenant_id' => $tenant->id,
            'title' => 'Infection Control 2026',
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'status' => 'active',
            'owner_id' => (string) $user->uuid,
        ]);

        $objective = Objective::create([
            'tenant_id' => $tenant->id,
            'workplan_id' => $workplan->id,
            'title' => 'Reduce SSI Rate',
        ]);

        $activity = Activity::create([
            'tenant_id' => $tenant->id,
            'objective_id' => $objective->id,
            'title' => 'Sterilization Audit Cycle',
        ]);

        $kpi = Kpi::create([
            'tenant_id' => $tenant->id,
            'activity_id' => $activity->id,
            'name' => 'Surgical Site Infection Rate',
            'direction' => 'decrease',
            'target_value' => 2.0,
            'unit' => '%',
        ]);

        // Submit KPI measurement that breaches threshold (recorded 5.5% > target 2.0% for decrease direction)
        $response = $this->actingAs($user)->post(
            route('quality-monitoring.workplans.kpi-update', ['workplan' => $workplan, 'kpi' => $kpi]),
            [
                'value' => 5.5,
                'note' => 'Spike observed post theatre sterilizer maintenance',
                'captured_at' => now()->toDateString(),
            ]
        );

        $response->assertSessionHas('status', 'success');

        $alert = Alert::where('workplan_id', $workplan->id)
            ->where('kpi_id', $kpi->id)
            ->first();

        expect($alert)->not->toBeNull()
            ->and($alert->status)->toBe('open')
            ->and($alert->sent_at)->toBeNull(); // Temporal state: must be null until physically delivered!
    });
});
