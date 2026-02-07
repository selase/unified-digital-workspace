<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Modules\ProjectManagement\Http\Requests\TaskAssignRequest;
use App\Modules\ProjectManagement\Http\Requests\TaskAttachmentStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\TaskCommentStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\TaskDependencyStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\TaskStoreRequest;
use App\Modules\ProjectManagement\Http\Requests\TaskUpdateRequest;
use App\Modules\ProjectManagement\Http\Requests\TimeEntryStoreRequest;
use App\Modules\ProjectManagement\Http\Resources\TaskAttachmentResource;
use App\Modules\ProjectManagement\Http\Resources\TaskCommentResource;
use App\Modules\ProjectManagement\Http\Resources\TaskDependencyResource;
use App\Modules\ProjectManagement\Http\Resources\TaskResource;
use App\Modules\ProjectManagement\Http\Resources\TimeEntryResource;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\TaskAssignment;
use App\Modules\ProjectManagement\Models\TaskAttachment;
use App\Modules\ProjectManagement\Models\TaskComment;
use App\Modules\ProjectManagement\Models\TaskDependency;
use App\Modules\ProjectManagement\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Spatie\Activitylog\Facades\Activity;

final class TaskController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('projects.view');

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['milestone', 'assignments', 'dependencies', 'dependents'])
            ->when(request('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when(request('priority'), fn (Builder $q, $priority) => $q->where('priority', $priority))
            ->when(request('assignee'), function (Builder $q, $assignee): void {
                $q->whereHas('assignments', fn (Builder $assignments) => $assignments->where('user_id', $assignee));
            })
            ->when(request('from_date'), fn (Builder $q, $from) => $q->whereDate('due_date', '>=', $from))
            ->when(request('to_date'), fn (Builder $q, $to) => $q->whereDate('due_date', '<=', $to))
            ->paginate(request()->integer('per_page', 15));

        return TaskResource::collection($tasks)->response();
    }

    public function store(TaskStoreRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();
        $this->assertMilestoneBelongs($project, Arr::get($data, 'milestone_id'));
        $this->assertParentBelongs($project, Arr::get($data, 'parent_id'));

        $data['project_id'] = $project->id;

        $task = Task::create($data);

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function update(TaskUpdateRequest $request, Project $project, Task $task): TaskResource
    {
        $this->ensureProjectRelationship($project, $task);

        $data = $request->validated();
        $this->assertMilestoneBelongs($project, Arr::get($data, 'milestone_id'));
        $this->assertParentBelongs($project, Arr::get($data, 'parent_id'));

        $task->fill($data);
        $task->save();

        return new TaskResource($task);
    }

    public function destroy(Project $project, Task $task): JsonResponse
    {
        $this->authorize('projects.tasks.manage');
        $this->ensureProjectRelationship($project, $task);

        $task->delete();

        return response()->json([], 204);
    }

    public function assign(TaskAssignRequest $request, Task $task): JsonResponse
    {
        $this->authorize('projects.tasks.manage');

        $assignment = TaskAssignment::updateOrCreate(
            ['task_id' => $task->id, 'user_id' => $request->string('user_id')],
            [
                'assigned_by_id' => $request->user()?->id,
                'assigned_at' => now(),
            ],
        );

        Activity::getFacadeRoot()
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties(['user_id' => $assignment->user_id])
            ->event('task_assigned')
            ->log('task assigned');

        return (new TaskResource($task->load('assignments')))->response();
    }

    public function addDependency(TaskDependencyStoreRequest $request, Task $task): JsonResponse
    {
        $this->authorize('projects.dependencies.manage');

        $dependsOnId = (int) $request->integer('depends_on_task_id');

        if ($task->id === $dependsOnId) {
            abort(422, 'Task cannot depend on itself.');
        }

        if ($this->createsCycle($task->id, $dependsOnId)) {
            abort(422, 'Dependency would create a cycle.');
        }

        $dependency = TaskDependency::create([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnId,
        ]);

        return (new TaskDependencyResource($dependency))->response()->setStatusCode(201);
    }

    public function removeDependency(Task $task, TaskDependency $dependency): JsonResponse
    {
        $this->authorize('projects.dependencies.manage');

        if ($dependency->task_id !== $task->id) {
            abort(404);
        }

        $dependency->delete();

        return response()->json([], 204);
    }

    public function comment(TaskCommentStoreRequest $request, Task $task): JsonResponse
    {
        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()?->id,
            'body' => $request->string('body'),
        ]);

        Activity::getFacadeRoot()
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties(['comment_id' => $comment->id])
            ->event('task_commented')
            ->log('task commented');

        return (new TaskCommentResource($comment))->response()->setStatusCode(201);
    }

    public function attach(TaskAttachmentStoreRequest $request, Task $task): JsonResponse
    {
        $path = Helper::processUploadedFile($request, 'file', 'task_'.$task->id, 'projects/tasks');
        $file = $request->file('file');

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getClientMimeType(),
            'size_bytes' => $file?->getSize() ?? 0,
            'uploaded_by_id' => $request->user()?->id,
        ]);

        Activity::getFacadeRoot()
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties(['attachment_id' => $attachment->id])
            ->event('task_attachment_added')
            ->log('task attachment added');

        return (new TaskAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function timeEntry(TimeEntryStoreRequest $request, Task $task): JsonResponse
    {
        $entry = TimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $request->user()?->id,
            'entry_date' => $request->date('entry_date'),
            'minutes' => $request->integer('minutes'),
            'note' => $request->input('note'),
        ]);

        return (new TimeEntryResource($entry))->response()->setStatusCode(201);
    }

    private function ensureProjectRelationship(Project $project, Task $task): void
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(404);
        }
    }

    private function assertMilestoneBelongs(Project $project, mixed $milestoneId): void
    {
        if ($milestoneId === null) {
            return;
        }

        $exists = Milestone::query()
            ->where('project_id', $project->id)
            ->where('id', $milestoneId)
            ->exists();

        if (! $exists) {
            abort(422, 'Milestone does not belong to project.');
        }
    }

    private function assertParentBelongs(Project $project, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $exists = Task::query()
            ->where('project_id', $project->id)
            ->where('id', $parentId)
            ->exists();

        if (! $exists) {
            abort(422, 'Parent task does not belong to project.');
        }
    }

    private function createsCycle(int $taskId, int $dependsOnId): bool
    {
        $seen = [$taskId];
        $stack = [$dependsOnId];

        while ($stack) {
            $current = array_pop($stack);
            if (in_array($current, $seen, true)) {
                return true;
            }
            $seen[] = $current;

            $next = TaskDependency::query()
                ->where('task_id', $current)
                ->pluck('depends_on_task_id')
                ->all();

            foreach ($next as $id) {
                $stack[] = (int) $id;
            }
        }

        return false;
    }
}
