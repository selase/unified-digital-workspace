<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class ProjectHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('projects.view'), 403);

        $apiLinks = collect([
            ['label' => 'Projects', 'route' => 'api.project-management.v1.projects.index', 'params' => []],
            ['label' => 'Project Summary', 'route' => 'api.project-management.v1.projects.summary', 'params' => ['project' => 1]],
            ['label' => 'Project Gantt', 'route' => 'api.project-management.v1.projects.gantt', 'params' => ['project' => 1]],
            ['label' => 'Project Tasks', 'route' => 'api.project-management.v1.projects.tasks.index', 'params' => ['project' => 1]],
        ])->map(function (array $apiLink): array {
            $apiLink['url'] = Route::has($apiLink['route']) ? route($apiLink['route'], $apiLink['params']) : null;

            return $apiLink;
        })->filter(fn (array $apiLink): bool => filled($apiLink['url']))->values();

        return view('project-management::index', [
            'rootApiUrl' => route('api.project-management.index'),
            'apiLinks' => $apiLinks,
            'featureCount' => count((array) config('modules.project-management.features', [])),
            'permissionCount' => count((array) config('modules.project-management.permissions', [])),
            'moduleVersion' => (string) config('modules.project-management.version', '1.0.0'),
        ]);
    }
}
