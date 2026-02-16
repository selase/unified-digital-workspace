<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Models\Incident;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class IncidentHubController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $baseQuery = Incident::query()
            ->visibleTo($request->user());

        $totalIncidents = (clone $baseQuery)->count();
        $resolvedIncidents = (clone $baseQuery)->whereNotNull('resolved_at')->count();
        $closedIncidents = (clone $baseQuery)->whereNotNull('closed_at')->count();
        $openIncidents = max(0, $totalIncidents - $resolvedIncidents - $closedIncidents);
        $overdueIncidents = (clone $baseQuery)
            ->whereNull('resolved_at')
            ->whereNull('closed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $atRiskIncidents = (clone $baseQuery)
            ->whereNull('resolved_at')
            ->whereNull('closed_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDay()])
            ->count();

        $recentIncidents = (clone $baseQuery)
            ->with(['status', 'priority', 'category'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $statusBreakdown = (clone $baseQuery)
            ->with('status:id,name')
            ->get()
            ->groupBy(function (Incident $incident): string {
                return (string) ($incident->status?->name ?: 'Unassigned');
            })
            ->map(fn ($incidents): int => $incidents->count())
            ->sortDesc();

        $priorityBreakdown = (clone $baseQuery)
            ->with('priority:id,name')
            ->get()
            ->groupBy(function (Incident $incident): string {
                return (string) ($incident->priority?->name ?: 'Unassigned');
            })
            ->map(fn ($incidents): int => $incidents->count())
            ->sortDesc();

        return view('incident-management::index', [
            'totalIncidents' => $totalIncidents,
            'openIncidents' => $openIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'closedIncidents' => $closedIncidents,
            'overdueIncidents' => $overdueIncidents,
            'atRiskIncidents' => $atRiskIncidents,
            'recentIncidents' => $recentIncidents,
            'statusBreakdown' => $statusBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
        ]);
    }
}
