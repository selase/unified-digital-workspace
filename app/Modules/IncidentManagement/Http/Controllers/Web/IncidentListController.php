<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Models\Incident;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class IncidentListController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Incident::class);

        $incidents = Incident::query()
            ->visibleTo($request->user())
            ->with(['status', 'priority', 'category'])
            ->latest('updated_at')
            ->paginate(20);

        return view('incident-management::incidents', [
            'incidents' => $incidents,
        ]);
    }
}
