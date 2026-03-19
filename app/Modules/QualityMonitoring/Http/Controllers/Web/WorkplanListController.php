<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Models\Workplan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class WorkplanListController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('qm.workplans.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $workplans = Workplan::query()
            ->withCount(['objectives', 'reviews'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['draft', 'active', 'submitted', 'approved', 'archived'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('quality-monitoring::workplans', [
            'workplans' => $workplans,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
