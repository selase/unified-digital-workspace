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

        $projects = Project::query()
            ->withCount(['tasks', 'members'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when(in_array($status, Project::STATUSES, true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('project-management::projects', [
            'projects' => $projects,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
