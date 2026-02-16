<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class QualityMonitoringHubController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('qm.workplans.view'), 403);

        $apiLinks = collect([
            ['label' => 'Workplans', 'route' => 'api.quality-monitoring.v1.workplans.index', 'params' => []],
            ['label' => 'Workplan Dashboard', 'route' => 'api.quality-monitoring.v1.workplans.dashboard', 'params' => ['workplan' => 1]],
            ['label' => 'Summary Report', 'route' => 'api.quality-monitoring.v1.workplans.reports.summary', 'params' => ['workplan' => 1]],
            ['label' => 'Variance Report', 'route' => 'api.quality-monitoring.v1.workplans.reports.variances', 'params' => ['workplan' => 1]],
            ['label' => 'Alerts', 'route' => 'api.quality-monitoring.v1.alerts.index', 'params' => []],
            ['label' => 'Indicators', 'route' => 'api.quality-monitoring.v1.indicators.index', 'params' => []],
        ])->map(function (array $apiLink): array {
            $apiLink['url'] = Route::has($apiLink['route']) ? route($apiLink['route'], $apiLink['params']) : null;

            return $apiLink;
        })->filter(fn (array $apiLink): bool => filled($apiLink['url']))->values();

        return view('quality-monitoring::index', [
            'rootApiUrl' => route('api.quality-monitoring.index'),
            'apiLinks' => $apiLinks,
            'featureCount' => count((array) config('modules.quality-monitoring.features', [])),
            'permissionCount' => count((array) config('modules.quality-monitoring.permissions', [])),
            'moduleVersion' => (string) config('modules.quality-monitoring.version', '1.0.0'),
        ]);
    }
}
