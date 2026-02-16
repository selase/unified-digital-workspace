<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentProgressReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class IncidentReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $reports = IncidentProgressReport::query()
            ->whereHas('incident', function ($query) use ($request): void {
                $query->visibleTo($request->user());
            })
            ->with(['incident:id,title,reference_code', 'user:id,first_name,last_name,email'])
            ->latest('created_at')
            ->paginate(20);

        $reportTotals = IncidentProgressReport::query()
            ->whereHas('incident', function ($query) use ($request): void {
                $query->visibleTo($request->user());
            })
            ->selectRaw('is_internal, count(*) as total')
            ->groupBy('is_internal')
            ->pluck('total', 'is_internal');

        return view('incident-management::reports', [
            'reports' => $reports,
            'internalReports' => (int) ($reportTotals[1] ?? 0),
            'externalReports' => (int) ($reportTotals[0] ?? 0),
        ]);
    }
}
