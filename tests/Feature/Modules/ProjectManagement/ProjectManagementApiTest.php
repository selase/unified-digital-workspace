<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TaskDependency;
use App\Services\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: User, 1: Tenant}
 */
function createProjectApiContext(): array
{
    $user = User::factory()->create();
    $tenant = setActiveTenantForTest($user);

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

it('creates and updates a project', function (): void {
    [$user] = createProjectApiContext();

    $response = actingAs($user, 'sanctum')->postJson('/api/project-management/v1/projects', [
        'name' => 'Website Redesign',
        'description' => 'Update marketing site',
        'priority' => 'high',
    ]);

    $response->assertCreated();

    $projectId = $response->json('data.id');

    actingAs($user, 'sanctum')
        ->putJson("/api/project-management/v1/projects/{$projectId}", [
            'status' => 'in-progress',
            'priority' => 'critical',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['status' => 'in-progress', 'priority' => 'critical']);
});

it('handles tasks with dependencies, comments, time entries, and attachments', function (): void {
    [$user] = createProjectApiContext();

    $project = Project::create([
        'name' => 'API Revamp',
        'slug' => Str::uuid(),
        'status' => 'planned',
    ]);

    $milestone = Milestone::create([
        'project_id' => $project->id,
        'name' => 'M1',
    ]);

    $taskA = Task::create([
        'project_id' => $project->id,
        'milestone_id' => $milestone->id,
        'title' => 'Design',
    ]);

    $taskB = Task::create([
        'project_id' => $project->id,
        'title' => 'Implement',
    ]);

    // Add dependency
    actingAs($user, 'sanctum')
        ->postJson("/api/project-management/v1/tasks/{$taskB->id}/dependencies", [
            'depends_on_task_id' => $taskA->id,
        ])
        ->assertCreated();

    expect(TaskDependency::where('task_id', $taskB->id)->exists())->toBeTrue();

    // Comment
    actingAs($user, 'sanctum')
        ->postJson("/api/project-management/v1/tasks/{$taskA->id}/comment", [
            'body' => 'Looks good',
        ])
        ->assertCreated();

    // Time entry
    actingAs($user, 'sanctum')
        ->postJson("/api/project-management/v1/tasks/{$taskA->id}/time-entries", [
            'entry_date' => now()->toDateString(),
            'minutes' => 60,
        ])
        ->assertCreated();
});

it('prevents allocation overlaps', function (): void {
    [$user] = createProjectApiContext();

    $project = Project::create([
        'name' => 'Mobile App',
        'slug' => Str::uuid(),
    ]);

    ResourceAllocation::create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'allocation_percent' => 50,
    ]);

    actingAs($user, 'sanctum')
        ->postJson("/api/project-management/v1/projects/{$project->id}/allocations", [
            'user_id' => $user->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'allocation_percent' => 40,
        ])
        ->assertStatus(422);
});

it('returns gantt data', function (): void {
    [$user] = createProjectApiContext();

    $project = Project::create([
        'name' => 'Data Platform',
        'slug' => Str::uuid(),
        'start_date' => now()->toDateString(),
    ]);

    Task::create([
        'project_id' => $project->id,
        'title' => 'ETL',
        'start_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $response = actingAs($user, 'sanctum')->getJson("/api/project-management/v1/projects/{$project->id}/gantt");

    $response->assertSuccessful()->assertJsonStructure([
        'project_id',
        'tasks' => [
            ['id', 'title', 'start_date', 'due_date', 'progress', 'depends_on'],
        ],
    ]);
});
