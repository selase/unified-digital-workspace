<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Http\Requests\ProjectStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\ProjectUpdateRequest;
use App\Modules\ProjectManagement\Http\Resources\ProjectResource;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('projects.view');

        $perPage = $request->integer('per_page', 15);

        $query = Project::query()
            ->with(['owner'])
            ->withCount(['tasks', 'milestones']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        if ($request->filled('member_id')) {
            $memberId = $request->input('member_id');
            $query->whereHas('members', fn ($q) => $q->where('user_id', $memberId));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_date', '>=', $request->date('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('end_date', '<=', $request->date('end_date'));
        }

        $projects = $query->paginate($perPage);

        return ProjectResource::collection($projects)->response();
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('projects.view');

        $project->load(['milestones', 'tasks', 'members', 'allocations']);

        return new ProjectResource($project);
    }

    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $project = Project::create($data);

        return (new ProjectResource($project))->response()->setStatusCode(201);
    }

    public function update(ProjectUpdateRequest $request, Project $project): ProjectResource
    {
        $data = $request->validated();

        if (isset($data['slug']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'] ?? $project->name);
        }

        $project->fill($data);
        $project->save();

        return new ProjectResource($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('projects.delete');

        $project->delete();

        return response()->json([], 204);
    }

    public function gantt(Project $project): JsonResponse
    {
        $this->authorize('projects.view');

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['dependencies'])
            ->get()
            ->map(function (Task $task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'start_date' => $task->start_date?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'progress' => $task->status === 'done' ? 100 : 0,
                    'depends_on' => $task->dependencies->pluck('depends_on_task_id')->all(),
                ];
            });

        return response()->json([
            'project_id' => $project->id,
            'tasks' => $tasks,
        ]);
    }

    public function summary(Project $project): JsonResponse
    {
        $this->authorize('projects.view');

        $tasks = Task::query()->where('project_id', $project->id);

        $statusBreakdown = $tasks
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $timeMinutes = $project->tasks()
            ->withSum('timeEntries as time_minutes', 'minutes')
            ->get()
            ->sum('time_minutes');

        $allocations = $project->allocations()
            ->selectRaw('user_id, sum(allocation_percent) as allocation')
            ->groupBy('user_id')
            ->pluck('allocation', 'user_id');

        return response()->json([
            'tasks_total' => $tasks->count(),
            'status_breakdown' => $statusBreakdown,
            'time_minutes' => $timeMinutes,
            'allocations' => $allocations,
        ]);
    }
}
