<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Database\Seeders;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectMember;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TaskAssignment;
use App\Modules\ProjectManagement\Models\TaskComment;
use App\Modules\ProjectManagement\Models\TimeEntry;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

final class ProjectManagementSeeder extends Seeder
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

        if (! Schema::connection($tenantConnection)->hasTable('projects')) {
            return;
        }

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->first() ?? User::query()->first();

        if (! $owner) {
            return;
        }

        $project = Project::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'slug' => 'workspace-modernization-program',
        ], [
            'name' => 'Workspace Modernization Program',
            'description' => 'Unified rollout for modules, permissions, and metronic UX migration.',
            'status' => 'in-progress',
            'priority' => 'high',
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(10)->toDateString(),
            'budget_amount' => 250_000,
            'currency' => 'USD',
            'owner_id' => (string) $owner->uuid,
            'metadata' => [
                'seed' => true,
                'portfolio' => 'core-platform',
            ],
        ]);

        ProjectMember::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'user_id' => (string) $owner->uuid,
        ], [
            'role' => 'Project Lead',
            'joined_at' => now()->subWeeks(2),
        ]);

        $milestone = Milestone::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Module Heads Delivery',
        ], [
            'description' => 'Deliver all module hub pages and navigation integration.',
            'due_date' => now()->addWeeks(3)->toDateString(),
            'sort_order' => 1,
        ]);

        $taskOne = Task::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Finalize tenant module navigation',
        ], [
            'milestone_id' => $milestone->id,
            'description' => 'Wire module-aware top menus and verify tenant permission checks.',
            'status' => 'in-progress',
            'priority' => 'high',
            'start_date' => now()->subDays(4)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'estimated_minutes' => 1_080,
            'sort_order' => 1,
        ]);

        $taskTwo = Task::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Seed completed modules with demo records',
        ], [
            'milestone_id' => $milestone->id,
            'description' => 'Generate representative records for hubs, tables, and analytics.',
            'status' => 'review',
            'priority' => 'medium',
            'start_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(2)->toDateString(),
            'estimated_minutes' => 420,
            'sort_order' => 2,
        ]);

        TaskAssignment::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'task_id' => $taskOne->id,
            'user_id' => (string) $owner->uuid,
        ], [
            'assigned_by_id' => (string) $owner->uuid,
            'assigned_at' => now()->subDays(4),
        ]);

        TaskAssignment::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'task_id' => $taskTwo->id,
            'user_id' => (string) $owner->uuid,
        ], [
            'assigned_by_id' => (string) $owner->uuid,
            'assigned_at' => now()->subDays(2),
        ]);

        TaskComment::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'task_id' => $taskOne->id,
            'body' => 'Navigation now maps module context to top menu clusters.',
        ], [
            'user_id' => (string) $owner->uuid,
        ]);

        TaskComment::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'task_id' => $taskTwo->id,
            'body' => 'Demo seeders are repeatable and safe to rerun.',
        ], [
            'user_id' => (string) $owner->uuid,
        ]);

        TimeEntry::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'task_id' => $taskOne->id,
            'user_id' => (string) $owner->uuid,
            'entry_date' => now()->subDay()->toDateString(),
        ], [
            'minutes' => 180,
            'note' => 'Completed module-aware dropdown wiring and permission checks.',
        ]);

        ResourceAllocation::query()->updateOrCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'user_id' => (string) $owner->uuid,
            'start_date' => now()->subWeek()->toDateString(),
        ], [
            'end_date' => now()->addWeeks(2)->toDateString(),
            'allocation_percent' => 60,
            'role' => 'Tech Lead',
        ]);
    }
}
