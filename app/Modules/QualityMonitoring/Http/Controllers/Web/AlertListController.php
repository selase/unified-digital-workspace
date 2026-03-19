<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Models\Alert;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AlertListController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('qm.alerts.manage'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $alerts = Alert::query()
            ->with(['workplan:id,title', 'kpi:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('type', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['open', 'acknowledged', 'resolved'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('quality-monitoring::alerts', [
            'alerts' => $alerts,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
