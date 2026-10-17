<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Milestone;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ProjectHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('projects.view'), 403);

        $projectsQuery = Project::query();
        $totalProjects = (clone $projectsQuery)->count();
        $activeProjects = (clone $projectsQuery)->where('status', 'in-progress')->count();
        $plannedProjects = (clone $projectsQuery)->where('status', 'planned')->count();
        $completedProjects = (clone $projectsQuery)->where('status', 'completed')->count();
        $onHoldProjects = (clone $projectsQuery)->where('status', 'on-hold')->count();

        $totalBudget = (clone $projectsQuery)->sum('budget_amount');

        $tasksQuery = Task::query();
        $totalTasks = (clone $tasksQuery)->count();
        $completedTasks = (clone $tasksQuery)->where('status', 'done')->count();
        $overdueTasks = (clone $tasksQuery)
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $recentProjects = Project::query()
            ->with(['owner:id,uuid,first_name,last_name,email'])
            ->withCount([
                'tasks',
                'members',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'done'),
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $upcomingMilestones = Milestone::query()
            ->with('project:id,uuid,name,slug')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        return view('project-management::index', [
            'totalProjects' => $totalProjects,
            'activeProjects' => $activeProjects,
            'plannedProjects' => $plannedProjects,
            'completedProjects' => $completedProjects,
            'onHoldProjects' => $onHoldProjects,
            'totalBudget' => (float) $totalBudget,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'overdueTasks' => $overdueTasks,
            'recentProjects' => $recentProjects,
            'upcomingMilestones' => $upcomingMilestones,
        ]);
    }
}
