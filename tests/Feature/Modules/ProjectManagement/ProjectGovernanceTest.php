<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectRisk;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * @return array{0: User, 1: Tenant}
 */
function createProjectGovernanceContext(): array
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

it('renders project edit charter view with current data', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Clinical Genomics Pipeline',
        'slug' => 'clinical-genomics',
        'status' => 'in-progress',
        'priority' => 'critical',
        'budget_amount' => 150000.00,
        'actual_cost_amount' => 25000.00,
        'owner_id' => (string) $user->uuid,
    ]);

    $response = $this->actingAs($user)->get(route('project-management.projects.edit', $project->uuid));

    $response->assertOk();
    $response->assertSee('Edit Project Charter');
    $response->assertSee('Clinical Genomics Pipeline');
    $response->assertSee('150000');
    $response->assertSee('25000');
});

it('updates project charter and logs activity when actual_cost_amount changes', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Automated Pathology Scanner',
        'slug' => 'pathology-scanner',
        'status' => 'planned',
        'priority' => 'high',
        'budget_amount' => 60000.00,
        'actual_cost_amount' => 0.00,
        'owner_id' => (string) $user->uuid,
    ]);

    $response = $this->actingAs($user)->put(route('project-management.projects.update', $project->uuid), [
        'name' => 'Automated Pathology Scanner v2',
        'status' => 'in-progress',
        'priority' => 'critical',
        'budget_amount' => 65000.00,
        'actual_cost_amount' => 12500.75,
        'currency' => 'USD',
        'description' => 'Updated charter description.',
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    $project->refresh();
    expect($project->name)->toBe('Automated Pathology Scanner v2');
    expect((float) $project->actual_cost_amount)->toBe(12500.75);
    expect((float) $project->budget_amount)->toBe(65000.00);

    $activity = Activity::query()
        ->where('log_name', 'project-management')
        ->where('subject_type', Project::class)
        ->where('subject_id', $project->id)
        ->where('event', 'project.actual_cost.updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->properties['actor_uuid'])->toBe((string) $user->uuid);
    expect((float) $activity->properties['old_actual_cost'])->toBe(0.0);
    expect((float) $activity->properties['new_actual_cost'])->toBe(12500.75);
    expect((float) $activity->properties['budget_amount'])->toBe(65000.00);
});

it('does not log activity when actual_cost_amount is unchanged in update', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Neonatal Monitoring Upgrade',
        'slug' => 'neonatal-upgrade',
        'status' => 'planned',
        'priority' => 'medium',
        'budget_amount' => 40000.00,
        'actual_cost_amount' => 5000.00,
        'owner_id' => (string) $user->uuid,
    ]);

    $initialCount = Activity::query()
        ->where('log_name', 'project-management')
        ->where('subject_id', $project->id)
        ->count();

    $this->actingAs($user)->put(route('project-management.projects.update', $project->uuid), [
        'name' => 'Neonatal Monitoring Upgrade Renamed',
        'status' => 'in-progress',
        'priority' => 'high',
        'budget_amount' => 40000.00,
        'actual_cost_amount' => 5000.00,
        'currency' => 'USD',
    ]);

    $newCount = Activity::query()
        ->where('log_name', 'project-management')
        ->where('subject_id', $project->id)
        ->count();

    expect($newCount)->toBe($initialCount);
});

it('soft deletes a project charter and redirects to projects index', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Legacy Radiology Storage',
        'slug' => 'legacy-rad-storage',
        'status' => 'cancelled',
        'owner_id' => (string) $user->uuid,
    ]);

    $response = $this->actingAs($user)->delete(route('project-management.projects.destroy', $project->uuid));

    $response->assertRedirect(route('project-management.projects.index'));

    expect(Project::find($project->id))->toBeNull();
    expect(Project::withTrashed()->find($project->id)?->trashed())->toBeTrue();
});

it('updates budget and actual spend via quick update with activity logging', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Oncology Infusion Automation',
        'slug' => 'oncology-infusion',
        'status' => 'in-progress',
        'budget_amount' => 80000.00,
        'actual_cost_amount' => 20000.00,
        'owner_id' => (string) $user->uuid,
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.budget.update', $project->uuid), [
        'budget_amount' => 85000.00,
        'actual_cost_amount' => 31200.00,
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    $project->refresh();
    expect((float) $project->actual_cost_amount)->toBe(31200.00);
    expect((float) $project->budget_amount)->toBe(85000.00);

    $activity = Activity::query()
        ->where('log_name', 'project-management')
        ->where('subject_id', $project->id)
        ->where('event', 'project.actual_cost.updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect((float) $activity->properties['old_actual_cost'])->toBe(20000.00);
    expect((float) $activity->properties['new_actual_cost'])->toBe(31200.00);
});

it('updates task details and reassigns team member', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();
    $engineer = User::factory()->create();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cardiology Pacemaker Sync',
        'slug' => 'pacemaker-sync',
        'status' => 'in-progress',
        'owner_id' => (string) $user->uuid,
    ]);

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Initial Firmware Inspection',
        'status' => 'todo',
        'priority' => 'medium',
        'estimated_minutes' => 120,
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.tasks.update', [
        'project' => $project->uuid,
        'task' => $task->uuid,
    ]), [
        'title' => 'Comprehensive Firmware Diagnostic',
        'priority' => 'high',
        'status' => 'in-progress',
        'estimated_hours' => 4.5,
        'assigned_to' => (string) $engineer->uuid,
        'description' => 'Upgraded diagnostic routine with crash dumps analysis.',
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    $task->refresh();
    expect($task->title)->toBe('Comprehensive Firmware Diagnostic');
    expect($task->priority)->toBe('high');
    expect($task->status)->toBe('in-progress');
    expect($task->estimated_minutes)->toBe(270);
    expect($task->assignments()->where('user_id', $engineer->uuid)->exists())->toBeTrue();
});

it('deletes a task from the project work breakdown', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Endoscopy Suite Refit',
        'slug' => 'endoscopy-refit',
        'status' => 'planned',
        'owner_id' => (string) $user->uuid,
    ]);

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Decommission Obsolete Monitor',
        'status' => 'todo',
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.tasks.destroy', [
        'project' => $project->uuid,
        'task' => $task->uuid,
    ]));

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));
    expect(Task::find($task->id))->toBeNull();
});

it('registers a project risk and verifies stored generated risk_score calculation', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Central Sterile Supply Upgrade',
        'slug' => 'cssd-upgrade',
        'status' => 'in-progress',
        'owner_id' => (string) $user->uuid,
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.risks.store', $project->uuid), [
        'title' => 'Autoclave Steam Supply Pressure Drop',
        'category' => 'technical',
        'likelihood' => 4,
        'impact' => 5,
        'status' => 'identified',
        'mitigation_strategy' => 'Install dual redundant pressure regulation valves',
        'contingency_plan' => 'Reroute to backup boiler circuit',
        'owner_id' => (string) $user->uuid,
        'description' => 'Main line fluctuations detected during morning sterilization cycles.',
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    /** @var ProjectRisk $risk */
    $risk = ProjectRisk::where('title', 'Autoclave Steam Supply Pressure Drop')->first();
    expect($risk)->not->toBeNull();
    expect($risk->likelihood)->toBe(4);
    expect($risk->impact)->toBe(5);
    // Database generated column: likelihood * impact = 20
    expect($risk->risk_score)->toBe(20);
    expect($risk->severityLevel())->toBe('critical');
    expect($risk->categoryLabel())->toBe('Technical & Architecture');
});

it('updates risk details, status and recalculates risk_score', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Laboratory LIMS Migration',
        'slug' => 'lims-migration',
        'status' => 'in-progress',
        'owner_id' => (string) $user->uuid,
    ]);

    /** @var ProjectRisk $risk */
    $risk = ProjectRisk::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Barcode Scanner Driver Incompatibility',
        'category' => 'technical',
        'likelihood' => 4,
        'impact' => 3,
        'status' => 'identified',
    ]);

    $risk->refresh();
    expect($risk->risk_score)->toBe(12);

    $response = $this->actingAs($user)->post(route('project-management.projects.risks.update', [
        'project' => $project->uuid,
        'risk' => $risk->uuid,
    ]), [
        'title' => 'Barcode Scanner Driver Incompatibility',
        'category' => 'technical',
        'likelihood' => 2,
        'impact' => 3,
        'status' => 'mitigated',
        'mitigation_strategy' => 'Firmware patch rolled out to all ward handhelds.',
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    $risk->refresh();
    expect($risk->likelihood)->toBe(2);
    expect($risk->impact)->toBe(3);
    // Stored generated column recalculated by DB: 2 * 3 = 6
    expect($risk->risk_score)->toBe(6);
    expect($risk->status)->toBe('mitigated');
    expect($risk->severityLevel())->toBe('medium');
});

it('removes a risk from the risk register', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Emergency Generator Overhaul',
        'slug' => 'generator-overhaul',
        'status' => 'planned',
        'owner_id' => (string) $user->uuid,
    ]);

    $risk = ProjectRisk::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Diesel Fuel Tank Contamination',
        'category' => 'operational',
        'likelihood' => 1,
        'impact' => 5,
        'status' => 'closed',
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.risks.destroy', [
        'project' => $project->uuid,
        'risk' => $risk->uuid,
    ]));

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));
    expect(ProjectRisk::find($risk->id))->toBeNull();
});

it('enforces PostgreSQL database CHECK constraints on likelihood and impact bounds (1 to 5)', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Constraint Boundary Validation',
        'slug' => 'constraint-validation',
        'status' => 'planned',
        'owner_id' => (string) $user->uuid,
    ]);

    // 1. likelihood > 5 must fail DB check constraint
    $likelihoodHighThrew = false;
    try {
        DB::table('project_risks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Out of bounds likelihood 6',
            'category' => 'operational',
            'likelihood' => 6,
            'impact' => 3,
            'status' => 'identified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $likelihoodHighThrew = true;
    }
    expect($likelihoodHighThrew)->toBeTrue();

    // 2. likelihood < 1 must fail DB check constraint
    $likelihoodLowThrew = false;
    try {
        DB::table('project_risks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Out of bounds likelihood 0',
            'category' => 'operational',
            'likelihood' => 0,
            'impact' => 3,
            'status' => 'identified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $likelihoodLowThrew = true;
    }
    expect($likelihoodLowThrew)->toBeTrue();

    // 3. impact > 5 must fail DB check constraint
    $impactHighThrew = false;
    try {
        DB::table('project_risks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Out of bounds impact 7',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 7,
            'status' => 'identified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $impactHighThrew = true;
    }
    expect($impactHighThrew)->toBeTrue();

    // 4. impact < 1 must fail DB check constraint
    $impactLowThrew = false;
    try {
        DB::table('project_risks')->insert([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Out of bounds impact 0',
            'category' => 'operational',
            'likelihood' => 3,
            'impact' => 0,
            'status' => 'identified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $e) {
        $impactLowThrew = true;
    }
    expect($impactLowThrew)->toBeTrue();
});

it('logs time on task with automatic hours to minutes conversion', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Dialysis Water Treatment',
        'slug' => 'dialysis-water',
        'status' => 'in-progress',
        'owner_id' => (string) $user->uuid,
    ]);

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Osmosis Membrane Backwash',
        'status' => 'in-progress',
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.time-entries.store', $project->uuid), [
        'task_id' => $task->id,
        'hours' => 3.5,
        'entry_date' => now()->toDateString(),
        'note' => 'Conducted backwash and checked effluent conductance levels.',
    ]);

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));

    $entry = TimeEntry::where('task_id', $task->id)->first();
    expect($entry)->not->toBeNull();
    // 3.5 hours * 60 = 210 minutes
    expect($entry->minutes)->toBe(210);
    expect((string) $entry->user_id)->toBe((string) $user->uuid);
    expect($entry->note)->toBe('Conducted backwash and checked effluent conductance levels.');
});

it('allows deleting time entry by creator or user with time.manage permission', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cath Lab Fluoroscopy Calibration',
        'slug' => 'cath-lab-cal',
        'status' => 'in-progress',
        'owner_id' => (string) $user->uuid,
    ]);

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Tube Output Voltage Verification',
        'status' => 'in-progress',
    ]);

    $entry = TimeEntry::create([
        'tenant_id' => $tenant->id,
        'task_id' => $task->id,
        'user_id' => (string) $user->uuid,
        'entry_date' => now()->toDateString(),
        'minutes' => 90,
        'note' => 'Initial test log',
    ]);

    $response = $this->actingAs($user)->post(route('project-management.projects.time-entries.destroy', [
        'project' => $project->uuid,
        'timeEntry' => $entry->id,
    ]));

    $response->assertRedirect(route('project-management.projects.show', $project->uuid));
    expect(TimeEntry::find($entry->id))->toBeNull();
});

it('rejects unauthorized users from performing governance actions (403)', function (): void {
    [$user, $tenant] = createProjectGovernanceContext();
    $unauthorized = User::factory()->create();
    $unauthorized->tenants()->attach($tenant->id);
    Session::put('active_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    app(TenantContext::class)->setTenant($tenant);

    $project = Project::create([
        'tenant_id' => $tenant->id,
        'name' => 'Protected Governance Project',
        'slug' => 'protected-project',
        'status' => 'in-progress',
        'owner_id' => (string) $user->uuid,
    ]);

    $task = Task::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Protected Task',
        'status' => 'todo',
    ]);

    $risk = ProjectRisk::create([
        'tenant_id' => $tenant->id,
        'project_id' => $project->id,
        'title' => 'Protected Risk',
        'likelihood' => 2,
        'impact' => 2,
        'status' => 'identified',
    ]);

    $entry = TimeEntry::create([
        'tenant_id' => $tenant->id,
        'task_id' => $task->id,
        'user_id' => (string) $user->uuid,
        'entry_date' => now()->toDateString(),
        'minutes' => 60,
    ]);

    // Unauthorized user attempts
    $this->actingAs($unauthorized)->get(route('project-management.projects.edit', $project->uuid))->assertForbidden();
    $this->actingAs($unauthorized)->put(route('project-management.projects.update', $project->uuid), ['name' => 'Hacked'])->assertForbidden();
    $this->actingAs($unauthorized)->delete(route('project-management.projects.destroy', $project->uuid))->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.budget.update', $project->uuid), ['actual_cost_amount' => 100])->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.tasks.update', [$project->uuid, $task->uuid]), ['title' => 'Hacked'])->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.tasks.destroy', [$project->uuid, $task->uuid]))->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.risks.store', $project->uuid), ['title' => 'Hacked'])->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.risks.update', [$project->uuid, $risk->uuid]), ['title' => 'Hacked'])->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.risks.destroy', [$project->uuid, $risk->uuid]))->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.time-entries.store', $project->uuid), ['task_id' => $task->id, 'hours' => 1, 'entry_date' => now()->toDateString()])->assertForbidden();
    $this->actingAs($unauthorized)->post(route('project-management.projects.time-entries.destroy', [$project->uuid, $entry->id]))->assertForbidden();
});

it('prevents cross-tenant and mismatched project-child operations (404)', function (): void {
    [$userA, $tenantA] = createProjectGovernanceContext();

    $projectA = Project::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Project Tenant A',
        'slug' => 'project-tenant-a',
        'status' => 'in-progress',
        'owner_id' => (string) $userA->uuid,
    ]);

    // Create Tenant B and Project B
    $userB = User::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userB->tenants()->attach($tenantB->id);
    app(ModuleManager::class)->enableForTenant('project-management', $tenantB);

    $projectB = Project::create([
        'tenant_id' => $tenantB->id,
        'name' => 'Project Tenant B',
        'slug' => 'project-tenant-b',
        'status' => 'in-progress',
        'owner_id' => (string) $userB->uuid,
    ]);

    $taskB = Task::create([
        'tenant_id' => $tenantB->id,
        'project_id' => $projectB->id,
        'title' => 'Task belonging to Project B',
        'status' => 'todo',
    ]);

    $riskB = ProjectRisk::create([
        'tenant_id' => $tenantB->id,
        'project_id' => $projectB->id,
        'title' => 'Risk belonging to Project B',
        'likelihood' => 3,
        'impact' => 3,
        'status' => 'identified',
    ]);

    $entryB = TimeEntry::create([
        'tenant_id' => $tenantB->id,
        'task_id' => $taskB->id,
        'user_id' => (string) $userB->uuid,
        'entry_date' => now()->toDateString(),
        'minutes' => 60,
    ]);

    // Tenant A user attempts to manipulate Project B child records under Project A:
    // 1. Task mismatch
    $this->actingAs($userA)->post(route('project-management.projects.tasks.update', [
        'project' => $projectA->uuid,
        'task' => $taskB->uuid,
    ]), ['title' => 'Cross-tenant task hijack'])->assertNotFound();

    $this->actingAs($userA)->post(route('project-management.projects.tasks.destroy', [
        'project' => $projectA->uuid,
        'task' => $taskB->uuid,
    ]))->assertNotFound();

    // 2. Risk mismatch
    $this->actingAs($userA)->post(route('project-management.projects.risks.update', [
        'project' => $projectA->uuid,
        'risk' => $riskB->uuid,
    ]), ['title' => 'Cross-tenant risk hijack'])->assertNotFound();

    $this->actingAs($userA)->post(route('project-management.projects.risks.destroy', [
        'project' => $projectA->uuid,
        'risk' => $riskB->uuid,
    ]))->assertNotFound();

    // 3. Time entry mismatch
    $this->actingAs($userA)->post(route('project-management.projects.time-entries.destroy', [
        'project' => $projectA->uuid,
        'timeEntry' => $entryB->id,
    ]))->assertNotFound();

    // 4. Access Project B directly under Tenant A context
    $this->actingAs($userA)->get(route('project-management.projects.show', $projectB->uuid))->assertNotFound();
});
