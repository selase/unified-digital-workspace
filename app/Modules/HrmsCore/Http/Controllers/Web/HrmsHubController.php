<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class HrmsHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.employees.view'), 403);

        $apiLinks = collect([
            ['label' => 'Employees', 'route' => 'api.hrms-core.v1.employees.index'],
            ['label' => 'Departments', 'route' => 'api.hrms-core.v1.departments.index'],
            ['label' => 'Leave Requests', 'route' => 'api.hrms-core.v1.leave-requests.index'],
            ['label' => 'Appraisals', 'route' => 'api.hrms-core.v1.appraisals.index'],
            ['label' => 'Promotions', 'route' => 'api.hrms-core.v1.promotions.index'],
            ['label' => 'Job Postings', 'route' => 'api.hrms-core.v1.job-postings.index'],
        ])->map(function (array $apiLink): array {
            $apiLink['url'] = Route::has($apiLink['route']) ? route($apiLink['route']) : null;

            return $apiLink;
        })->filter(fn (array $apiLink): bool => filled($apiLink['url']))->values();

        return view('hrms-core::index', [
            'rootApiUrl' => route('api.hrms-core.index'),
            'apiLinks' => $apiLinks,
            'featureCount' => count((array) config('modules.hrms-core.features', [])),
            'permissionCount' => count((array) config('modules.hrms-core.permissions', [])),
            'moduleVersion' => (string) config('modules.hrms-core.version', '1.0.0'),
        ]);
    }
}
