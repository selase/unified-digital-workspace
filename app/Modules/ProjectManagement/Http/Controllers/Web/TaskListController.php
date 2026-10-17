<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TaskListController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('projects.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $tasks = Task::query()
            ->with([
                'project:id,uuid,name,slug',
                'milestone:id,name',
                'assignments.user:id,uuid,first_name,last_name,email',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, Task::STATUSES, true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('project-management::tasks', [
            'tasks' => $tasks,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        abort_if(! $request->user()?->can('projects.tasks.manage') && ! $request->user()?->can('projects.update'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
        ]);

        $task->update([
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === 'done' ? now() : null,
        ]);

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', 'Task status updated to '.ucfirst($validated['status']).'.');
    }
}
