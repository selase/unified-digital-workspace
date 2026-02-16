<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentTask;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class IncidentTaskBoardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $tasks = IncidentTask::query()
            ->whereHas('incident', function ($query) use ($request): void {
                $query->visibleTo($request->user());
            })
            ->with(['incident:id,title,reference_code', 'assignedTo:id,first_name,last_name,email'])
            ->latest('updated_at')
            ->paginate(25);

        $statusCounts = IncidentTask::query()
            ->whereHas('incident', function ($query) use ($request): void {
                $query->visibleTo($request->user());
            })
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('incident-management::tasks', [
            'tasks' => $tasks,
            'statusCounts' => $statusCounts,
        ]);
    }
}
