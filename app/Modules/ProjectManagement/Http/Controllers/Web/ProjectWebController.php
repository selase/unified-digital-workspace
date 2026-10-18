<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\ProjectManagement\Http\Requests\ProjectStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\ProjectUpdateRequest;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectMember;
use App\Modules\ProjectManagement\Models\ProjectRisk;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TaskAssignment;
use App\Modules\ProjectManagement\Models\TimeEntry;
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
        $data['actual_cost_amount'] = 0.00;

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
                'timeEntries' => fn ($te) => $te->with('user:id,uuid,first_name,last_name,email')->latest('entry_date'),
            ])->orderBy('sort_order')->latest('id'),
            'allocations' => fn ($q) => $q->with('user:id,uuid,first_name,last_name,email'),
            'risks' => fn ($q) => $q->with('owner:id,uuid,first_name,last_name,email')->latest('id'),
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

        /** @var array<int, array<int, list<ProjectRisk>>> $riskMatrix */
        $riskMatrix = [];
        for ($l = 5; $l >= 1; $l--) {
            for ($i = 1; $i <= 5; $i++) {
                $riskMatrix[$l][$i] = [];
            }
        }
        foreach ($project->risks as $r) {
            $riskMatrix[$r->likelihood][$r->impact][] = $r;
        }

        $allTimeEntries = $project->tasks->flatMap->timeEntries->sortByDesc('entry_date')->values();
        $totalTimeMinutes = (int) $allTimeEntries->sum('minutes');
        $totalTimeHours = round($totalTimeMinutes / 60, 1);

        return view('project-management::show', [
            'project' => $project,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'progressPercent' => $progressPercent,
            'totalMilestones' => $totalMilestones,
            'completedMilestones' => $completedMilestones,
            'users' => $users,
            'riskMatrix' => $riskMatrix,
            'allTimeEntries' => $allTimeEntries,
            'totalTimeHours' => $totalTimeHours,
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        abort_if(! $request->user()?->can('projects.update'), 403);

        $users = User::query()
            ->select(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->orderBy('first_name')
            ->get();

        return view('project-management::edit', [
            'project' => $project,
            'users' => $users,
        ]);
    }

    public function update(ProjectUpdateRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        $oldActual = (float) $project->actual_cost_amount;
        $newActual = isset($validated['actual_cost_amount']) ? (float) $validated['actual_cost_amount'] : $oldActual;

        if (array_key_exists('actual_cost_amount', $validated) && $oldActual !== $newActual) {
            activity('project-management')
                ->performedOn($project)
                ->causedBy($request->user())
                ->withProperties([
                    'actor_id' => $request->user()?->id,
                    'actor_uuid' => $request->user()?->uuid,
                    'actor_name' => $request->user()?->first_name.' '.$request->user()?->last_name,
                    'old_actual_cost' => $oldActual,
                    'new_actual_cost' => $newActual,
                    'budget_amount' => $validated['budget_amount'] ?? $project->budget_amount,
                ])
                ->event('project.actual_cost.updated')
                ->log('project.actual_cost.updated');
        }

        $project->update($validated);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Project Charter updated successfully.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.delete'), 403);

        $name = $project->name;
        $project->delete();

        return redirect()
            ->route('project-management.projects.index')
            ->with('status', 'success')
            ->with('message', "Project '{$name}' archived successfully.");
    }

    public function updateBudget(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_cost_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $oldActual = (float) $project->actual_cost_amount;
        $newActual = (float) $validated['actual_cost_amount'];

        if ($oldActual !== $newActual) {
            $user = $request->user();
            activity('project-management')
                ->performedOn($project)
                ->causedBy($user)
                ->withProperties([
                    'actor_id' => $user->id,
                    'actor_uuid' => $user->uuid,
                    'actor_name' => $user->first_name.' '.$user->last_name,
                    'old_actual_cost' => $oldActual,
                    'new_actual_cost' => $newActual,
                    'budget_amount' => $validated['budget_amount'] ?? $project->budget_amount,
                ])
                ->event('project.actual_cost.updated')
                ->log('project.actual_cost.updated');
        }

        $project->update($validated);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Project budget & actual spend updated successfully.');
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

    public function updateTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.tasks.manage') && ! $request->user()?->can('projects.update'), 403);
        abort_if($task->project_id !== $project->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'milestone_id' => ['nullable', 'integer', Rule::exists('milestones', 'id')->where('project_id', $project->id)],
            'priority' => ['nullable', 'string', Rule::in(Task::PRIORITIES)],
            'status' => ['nullable', 'string', Rule::in(Task::STATUSES)],
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $estimatedMinutes = isset($validated['estimated_hours']) && $validated['estimated_hours'] !== ''
            ? (int) round((float) $validated['estimated_hours'] * 60)
            : $task->estimated_minutes;

        $status = $validated['status'] ?? $task->status;

        $task->update([
            'milestone_id' => $validated['milestone_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? $task->priority,
            'status' => $status,
            'due_date' => $validated['due_date'] ?? null,
            'estimated_minutes' => $estimatedMinutes,
            'completed_at' => $status === 'done' ? ($task->completed_at ?? now()) : null,
        ]);

        if (array_key_exists('assigned_to', $validated)) {
            TaskAssignment::where('task_id', $task->id)->delete();
            if (! empty($validated['assigned_to'])) {
                TaskAssignment::create([
                    'tenant_id' => $project->tenant_id,
                    'task_id' => $task->id,
                    'user_id' => $validated['assigned_to'],
                    'assigned_by_id' => (string) $request->user()->uuid,
                    'assigned_at' => now(),
                ]);
            }
        }

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Task '{$task->title}' updated.");
    }

    public function destroyTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.tasks.manage') && ! $request->user()?->can('projects.update'), 403);
        abort_if($task->project_id !== $project->id, 404);

        $title = $task->title;
        $task->delete();

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Task '{$title}' removed from work breakdown.");
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

    public function storeRisk(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(ProjectRisk::CATEGORIES))],
            'likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'impact' => ['required', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'string', Rule::in(array_keys(ProjectRisk::STATUSES))],
            'mitigation_strategy' => ['nullable', 'string', 'max:3000'],
            'contingency_plan' => ['nullable', 'string', 'max:3000'],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $risk = ProjectRisk::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'likelihood' => (int) $validated['likelihood'],
            'impact' => (int) $validated['impact'],
            'status' => $validated['status'],
            'mitigation_strategy' => $validated['mitigation_strategy'] ?? null,
            'contingency_plan' => $validated['contingency_plan'] ?? null,
            'owner_id' => $validated['owner_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Risk '{$risk->title}' registered in risk governance matrix.");
    }

    public function updateRisk(Request $request, Project $project, ProjectRisk $risk): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.update'), 403);
        abort_if($risk->project_id !== $project->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys(ProjectRisk::CATEGORIES))],
            'likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'impact' => ['required', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'string', Rule::in(array_keys(ProjectRisk::STATUSES))],
            'mitigation_strategy' => ['nullable', 'string', 'max:3000'],
            'contingency_plan' => ['nullable', 'string', 'max:3000'],
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        $risk->update([
            'title' => $validated['title'],
            'category' => $validated['category'],
            'likelihood' => (int) $validated['likelihood'],
            'impact' => (int) $validated['impact'],
            'status' => $validated['status'],
            'mitigation_strategy' => $validated['mitigation_strategy'] ?? null,
            'contingency_plan' => $validated['contingency_plan'] ?? null,
            'owner_id' => $validated['owner_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Risk '{$risk->title}' updated.");
    }

    public function destroyRisk(Request $request, Project $project, ProjectRisk $risk): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.update'), 403);
        abort_if($risk->project_id !== $project->id, 404);

        $title = $risk->title;
        $risk->delete();

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', "Risk '{$title}' removed from register.");
    }

    public function storeTimeEntry(Request $request, Project $project): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.time.manage') && ! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'task_id' => ['required', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)],
            'entry_date' => ['required', 'date'],
            'hours' => ['nullable', 'numeric', 'min:0.1', 'max:24'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $minutes = isset($validated['minutes']) && (int) $validated['minutes'] > 0
            ? (int) $validated['minutes']
            : (int) round(((float) ($validated['hours'] ?? 0)) * 60);

        if ($minutes <= 0) {
            return back()->withErrors(['hours' => 'Please provide valid hours or minutes.']);
        }

        TimeEntry::create([
            'tenant_id' => $project->tenant_id,
            'task_id' => (int) $validated['task_id'],
            'user_id' => (string) $request->user()->uuid,
            'entry_date' => $validated['entry_date'],
            'minutes' => $minutes,
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Time logged successfully.');
    }

    public function destroyTimeEntry(Request $request, Project $project, TimeEntry $timeEntry): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.time.manage') && (string) $request->user()?->uuid !== (string) $timeEntry->user_id, 403);
        abort_if($timeEntry->task?->project_id !== $project->id, 404);

        $timeEntry->delete();

        return redirect()
            ->route('project-management.projects.show', $project->uuid)
            ->with('status', 'success')
            ->with('message', 'Time entry deleted.');
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
