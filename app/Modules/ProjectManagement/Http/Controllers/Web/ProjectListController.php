<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ProjectListController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('projects.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $priority = (string) $request->string('priority');

        $projects = Project::query()
            ->with(['owner:id,uuid,first_name,last_name,email'])
            ->withCount([
                'tasks',
                'members',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, Project::STATUSES, true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when(in_array($priority, Project::PRIORITIES, true), function ($query) use ($priority): void {
                $query->where('priority', $priority);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('project-management::projects', [
            'projects' => $projects,
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
        ]);
    }
}
