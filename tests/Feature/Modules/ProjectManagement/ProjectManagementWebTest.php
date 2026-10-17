<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectMember;
use App\Modules\ProjectManagement\Models\Task;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createProjectWebContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $permissions = [
        'projects.view',
        'projects.create',
        'projects.update',
        'projects.delete',
        'projects.tasks.manage',
        'projects.milestones.manage',
        'projects.dependencies.manage',
        'projects.time.manage',
        'projects.allocations.manage',
        'projects.members.manage',
        'projects.attachments.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'category' => 'projects',
            'guard_name' => 'web',
        ], [
            'uuid' => (string) Str::uuid(),
        ]);
    }

    $user->givePermissionTo($permissions);
    app(ModuleManager::class)->enableForTenant('project-management', $tenant);

    Artisan::call('migrate', [
        '--database' => 'landlord',
        '--path' => app_path('Modules/ProjectManagement/Database/Migrations'),
        '--realpath' => true,
    ]);

    return [$user, $tenant];
}

it('renders project management hub with live statistics', function (): void {
    [$user, $tenant] = createProjectWebContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'EHR Interoperability Platform',
        'slug' => 'ehr-interop',
        'status' => 'in-progress',
        'priority' => 'critical',
        'budget_amount' => 125000.00,
        'currency' => 'USD',
        'owner_id' => (string) $user->uuid,
    ]);

    Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Deploy FHIR API Gateway',
        'status' => 'done',
    ]);

    Milestone::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Gate 1 Sign-Off',
        'due_date' => now()->addMonth(),
    ]);

    $response = $this->actingAs($user)->get(route('project-management.index'));

    $response->assertOk();
    $response->assertSee('Project Management & Governance Hub');
    $response->assertSee('EHR Interoperability Platform');
    $response->assertSee('Gate 1 Sign-Off');
    $response->assertSee('In Execution');
});

it('renders projects register and filters by status and priority', function (): void {
    [$user, $tenant] = createProjectWebContext();

    Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Digital Radiology Workflow',
        'slug' => 'rad-workflow',
        'status' => 'planned',
        'priority' => 'high',
    ]);

    Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Laboratory Automation',
        'slug' => 'lab-automation',
        'status' => 'in-progress',
        'priority' => 'medium',
    ]);

    // Unfiltered view
    $response = $this->actingAs($user)->get(route('project-management.projects.index'));
    $response->assertOk();
    $response->assertSee('Digital Radiology Workflow');
    $response->assertSee('Laboratory Automation');

    // Filter by planned status
    $filtered = $this->actingAs($user)->get(route('project-management.projects.index', ['status' => 'planned']));
    $filtered->assertOk();
    $filtered->assertSee('Digital Radiology Workflow');
    $filtered->assertDontSee('Laboratory Automation');
});

it('renders project charter create form', function (): void {
    [$user] = createProjectWebContext();

    $response = $this->actingAs($user)->get(route('project-management.projects.create'));

    $response->assertOk();
    $response->assertSee('Initialize Project Charter');
    $response->assertSee('Project Title');
    $response->assertSee('Financial Allocation');
});

it('creates a new project charter with auto-generated slug and lead membership', function (): void {
    [$user] = createProjectWebContext();

    $response = $this->actingAs($user)->post(route('project-management.projects.store'), [
        'name' => 'Telemedicine Outreach 2026',
        'priority' => 'high',
        'status' => 'planned',
        'budget_amount' => 45000.00,
        'currency' => 'USD',
        'description' => 'Expand remote clinical consultations to district health centres.',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
    ]);

    $project = Project::where('name', 'Telemedicine Outreach 2026')->first();
    expect($project)->not->toBeNull();
    expect($project->slug)->toBe('telemedicine-outreach-2026');
    expect($project->owner_id)->toBe((string) $user->uuid);

    // Verify creator was added as Project Lead member
    $member = ProjectMember::where('project_id', $project->id)->where('user_id', $user->uuid)->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe('Project Lead');

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));
});

it('renders project workspace with deliverables, milestones, and governance history', function (): void {
    [$user, $tenant] = createProjectWebContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'ICU Bedside Monitoring Telemetry',
        'slug' => 'icu-telemetry',
        'status' => 'in-progress',
        'priority' => 'critical',
        'budget_amount' => 80000.00,
        'owner_id' => (string) $user->uuid,
        'description' => 'Real-time telemetry integration with central clinical dashboards.',
    ]);

    $milestone = Milestone::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'name' => 'Network Sensor Calibration',
        'due_date' => now()->addWeeks(2),
    ]);

    Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'milestone_id' => $milestone->id,
        'title' => 'Mount Gateway Transceivers',
        'status' => 'in-progress',
        'priority' => 'high',
    ]);

    $response = $this->actingAs($user)->get(route('project-management.projects.show', $project->uuid));

    $response->assertOk();
    $response->assertSee('ICU Bedside Monitoring Telemetry');
    $response->assertSee('Network Sensor Calibration');
    $response->assertSee('Mount Gateway Transceivers');
    $response->assertSee('Stage-Gate Review');
});

it('executes stage-gate transition with audit history and completion timestamp', function (): void {
    [$user, $tenant] = createProjectWebContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Pharmacy Robotic Dispensing',
        'slug' => 'pharmacy-robotics',
        'status' => 'in-progress',
        'priority' => 'high',
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.stage-gate', $project->uuid), [
        'status' => 'completed',
        'stage_note' => 'Operational commissioning successful and accepted by Chief Pharmacist.',
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    $project->refresh();
    expect($project->status)->toBe('completed');
    expect($project->completed_at)->not->toBeNull();

    $history = $project->metadata['stage_gate_history'] ?? [];
    expect($history)->toHaveCount(1);
    expect($history[0]['from_status'])->toBe('in-progress');
    expect($history[0]['to_status'])->toBe('completed');
    expect($history[0]['note'])->toBe('Operational commissioning successful and accepted by Chief Pharmacist.');
});

it('creates and toggles milestone completion', function (): void {
    [$user, $tenant] = createProjectWebContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cardiology Pacemaker Registry',
        'slug' => 'pacemaker-reg',
        'status' => 'planned',
    ]);

    // Create milestone
    $this->actingAs($user)->post(route('project-management.projects.milestones.store', $project->uuid), [
        'name' => 'Gate 1: Ethics Review Approval',
        'description' => 'IRB approval certificate received',
        'due_date' => now()->addDays(14)->toDateString(),
    ])->assertRedirect(route('project-management.projects.show', $project->uuid));

    $milestone = Milestone::where('project_id', $project->id)->first();
    expect($milestone)->not->toBeNull();
    expect($milestone->name)->toBe('Gate 1: Ethics Review Approval');
    expect($milestone->completed_at)->toBeNull();

    // Toggle milestone to completed
    $this->actingAs($user)->post(route('project-management.projects.milestones.toggle', [
        'project' => $project->uuid,
        'milestone' => $milestone->id,
    ]))->assertRedirect(route('project-management.projects.show', $project->uuid));

    $milestone->refresh();
    expect($milestone->completed_at)->not->toBeNull();

    // Toggle back to pending
    $this->actingAs($user)->post(route('project-management.projects.milestones.toggle', [
        'project' => $project->uuid,
        'milestone' => $milestone->id,
    ]))->assertRedirect(route('project-management.projects.show', $project->uuid));

    $milestone->refresh();
    expect($milestone->completed_at)->toBeNull();
});

it('creates a task with assignment and updates status', function (): void {
    [$user, $tenant] = createProjectWebContext();
    $assignee = User::factory()->create();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Oncology Clinical Trials Registry',
        'slug' => 'oncology-trials',
        'status' => 'in-progress',
    ]);

    // Store task
    $this->actingAs($user)->post(route('project-management.projects.tasks.store', $project->uuid), [
        'title' => 'Draft Patient Consent Form',
        'priority' => 'high',
        'due_date' => now()->addDays(7)->toDateString(),
        'assigned_to' => (string) $assignee->uuid,
        'estimated_hours' => 8.5,
    ])->assertRedirect(route('project-management.projects.show', $project->uuid));

    $task = Task::where('project_id', $project->id)->first();
    expect($task)->not->toBeNull();
    expect($task->title)->toBe('Draft Patient Consent Form');
    expect($task->estimated_minutes)->toBe(510);
    expect($task->status)->toBe('todo');
    expect($task->assignments()->where('user_id', $assignee->uuid)->exists())->toBeTrue();

    // Update status to done
    $this->actingAs($user)->post(route('project-management.projects.tasks.status', [
        'project' => $project->uuid,
        'task' => $task->uuid,
    ]), [
        'status' => 'done',
    ])->assertRedirect(route('project-management.projects.show', $project->uuid));

    $task->refresh();
    expect($task->status)->toBe('done');
    expect($task->completed_at)->not->toBeNull();
});

it('adds team member to project', function (): void {
    [$user, $tenant] = createProjectWebContext();
    $colleague = User::factory()->create();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Surgical Navigation Suite',
        'slug' => 'surgical-nav',
        'status' => 'planned',
    ]);

    $this->actingAs($user)->post(route('project-management.projects.members.store', $project->uuid), [
        'user_id' => (string) $colleague->uuid,
        'role' => 'Biomedical Engineer',
    ])->assertRedirect(route('project-management.projects.show', $project->uuid));

    $member = ProjectMember::where('project_id', $project->id)->where('user_id', $colleague->uuid)->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe('Biomedical Engineer');
});

it('renders global task board and updates task status', function (): void {
    [$user, $tenant] = createProjectWebContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Emergency Triage Optimization',
        'slug' => 'er-triage',
        'status' => 'in-progress',
    ]);

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Install Kiosk Hardware',
        'status' => 'todo',
        'priority' => 'high',
    ]);

    $response = $this->actingAs($user)->get(route('project-management.tasks.index'));
    $response->assertOk();
    $response->assertSee('Install Kiosk Hardware');
    $response->assertSee('Emergency Triage Optimization');

    // Update status via global board
    $this->actingAs($user)->post(route('project-management.tasks.status', $task->uuid), [
        'status' => 'in-progress',
    ])->assertRedirect();

    $task->refresh();
    expect($task->status)->toBe('in-progress');
});

it('denies access to unauthorized users without permissions', function (): void {
    $unauthorizedUser = User::factory()->create();
    $tenant = setActiveTenantForTest($unauthorizedUser);
    app(ModuleManager::class)->enableForTenant('project-management', $tenant);

    $this->actingAs($unauthorizedUser)->get(route('project-management.index'))->assertForbidden();
    $this->actingAs($unauthorizedUser)->get(route('project-management.projects.index'))->assertForbidden();
    $this->actingAs($unauthorizedUser)->get(route('project-management.projects.create'))->assertForbidden();
});
