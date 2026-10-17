<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Http\Requests\ProjectStoreRequest;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectMember;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TaskAssignment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProjectWebController extends Controller
{
    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('projects.create'), 403);

        $users = User::query()
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('project-management::create', [
            'users' => $users,
        ]);
    }

    public function store(ProjectStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug !== '' ? $baseSlug : 'project';
            $counter = 1;
            while (Project::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.(++$counter);
            }
            $data['slug'] = $slug;
        }

        $data['owner_id'] = $data['owner_id'] ?? (string) $request->user()?->uuid;
        $data['status'] = $data['status'] ?? 'planned';
        $data['priority'] = $data['priority'] ?? 'medium';
        $data['currency'] = $data['currency'] ?? 'USD';

        /** @var Project $project */
        $project = DB::transaction(function () use ($data): Project {
            /** @var Project $created */
            $created = Project::create($data);

            if ($created->owner_id) {
                ProjectMember::create([
                    'tenant_id' => $created->tenant_id,
                    'project_id' => $created->id,
                    'user_id' => $created->owner_id,
                    'role' => 'Project Lead',
                    'joined_at' => now(),
                ]);
            }

            return $created;
        });

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Project Charter created successfully. Welcome to your project workspace!');
    }

    public function show(Request $request, Project $project): View
    {
        abort_if(! $request->user()?->can('projects.view'), 403);

        $project->load([
            'owner:id,uuid,first_name,last_name,email',
            'members' => fn ($q) => $q->with('user:id,uuid,first_name,last_name,email')->latest('id'),
            'milestones' => fn ($q) => $q->withCount('tasks')->orderBy('sort_order')->orderBy('due_date'),
            'tasks' => fn ($q) => $q->with([
                'milestone:id,name',
                'assignments.user:id,uuid,first_name,last_name,email',
            ])->orderBy('sort_order')->latest('id'),
            'allocations' => fn ($q) => $q->with('user:id,uuid,first_name,last_name,email'),
        ]);

        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'done')->count();
        $progressPercent = $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;

        $totalMilestones = $project->milestones->count();
        $completedMilestones = $project->milestones->whereNotNull('completed_at')->count();

        $users = User::query()
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('project-management::show', [
            'project' => $project,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'progressPercent' => $progressPercent,
            'totalMilestones' => $totalMilestones,
            'completedMilestones' => $completedMilestones,
            'users' => $users,
        ]);
    }

    public function reviewStageGate(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Project::STATUSES)],
            'stage_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $validated['status'];
        $completedAt = $status === 'completed' ? ($project->completed_at ?? now()) : null;

        $metadata = $project->metadata ?? [];
        /** @var list<array<string, mixed>> $history */
        $history = is_array($metadata['stage_gate_history'] ?? null) ? $metadata['stage_gate_history'] : [];
        $history[] = [
            'from_status' => $project->status,
            'to_status' => $status,
            'reviewer_uuid' => (string) $request->user()->uuid,
            'note' => $validated['stage_note'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];
        $metadata['stage_gate_history'] = $history;

        $project->update([
            'status' => $status,
            'completed_at' => $completedAt,
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Project stage-gate updated to '.ucfirst($status).'.');
    }

    public function storeMilestone(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.milestones.manage') && ! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        Milestone::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Milestone '{$validated['name']}' added to project.");
    }

    public function toggleMilestone(Request $request, Project $project, Milestone $milestone): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.milestones.manage') && ! $request->user()?->can('projects.update'), 403);
        abort_if($milestone->project_id !== $project->id, 404);

        $isCompleted = $milestone->completed_at !== null;
        $milestone->update([
            'completed_at' => $isCompleted ? null : now(),
        ]);

        $stateText = $isCompleted ? 'marked as pending' : 'marked as completed';

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Milestone '{$milestone->name}' {$stateText}.");
    }

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.tasks.manage') && ! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'milestone_id' => ['nullable', 'integer', Rule::exists('milestones', 'id')->where('project_id', $project->id)],
            'priority' => ['nullable', 'string', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $estimatedMinutes = isset($validated['estimated_hours']) && $validated['estimated_hours'] !== ''
            ? (int) round((float) $validated['estimated_hours'] * 60)
            : null;

        /** @var Task $task */
        $task = Task::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'milestone_id' => $validated['milestone_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'todo',
            'due_date' => $validated['due_date'] ?? null,
            'estimated_minutes' => $estimatedMinutes,
        ]);

        if (! empty($validated['assigned_to'])) {
            TaskAssignment::create([
                'tenant_id' => $project->tenant_id,
                'task_id' => $task->id,
                'user_id' => $validated['assigned_to'],
                'assigned_by_id' => (string) $request->user()->uuid,
                'assigned_at' => now(),
            ]);
        }

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Task '{$task->title}' added to work breakdown.");
    }

    public function updateTaskStatus(Request $request, Project $project, Task $task): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.tasks.manage') && ! $request->user()?->can('projects.update'), 403);
        abort_if($task->project_id !== $project->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
        ]);

        $task->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'done' ? now() : null,
        ]);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Task status changed to '.ucfirst($validated['status']).'.');
    }

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.members.manage') && ! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', Rule::exists('users', 'uuid')],
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $exists = ProjectMember::where('project_id', $project->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if (! $exists) {
            ProjectMember::create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'user_id' => $validated['user_id'],
                'role' => $validated['role'] ?: 'Team Member',
                'joined_at' => now(),
            ]);
        }

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Team member added to project.');
    }
}
