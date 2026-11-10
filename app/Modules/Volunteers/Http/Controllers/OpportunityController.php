<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\ReportCsv;
use App\Modules\Finance\Services\ReportPdf;
use App\Modules\Finance\Support\ResolvesFinanceTenant;
use App\Modules\Volunteers\Http\Requests\SaveOpportunityRequest;
use App\Modules\Volunteers\Models\VolOpportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OpportunityController extends Controller
{
    use ResolvesFinanceTenant;

    public function index(Request $request): View
    {
        $tenantId = $this->allow($request, 'volunteers.view');

        return view('volunteers::opportunities.index', [
            'opportunities' => VolOpportunity::query()->where('tenant_id', $tenantId)->with('volunteers')->withCount('volunteers')->orderByRaw("case status when 'open' then 0 else 1 end")->orderBy('starts_on')->get(),
        ]);
    }

    public function store(SaveOpportunityRequest $request): RedirectResponse
    {
        VolOpportunity::query()->create([...$request->validated(), 'spots' => (int) ($request->validated('spots') ?? 0), 'status' => 'open', 'tenant_id' => $this->tenantId($request)]);

        return back()->with('success', 'Opportunity created.');
    }

    /** The sign-up list for an opportunity, as CSV (default) or a printable PDF, for the person running the day. */
    public function roster(Request $request, ReportCsv $csv, ReportPdf $pdf, string $opportunity): StreamedResponse|\Illuminate\Http\Response
    {
        $tenantId = $this->allow($request, 'volunteers.view');
        $model = VolOpportunity::query()->where('tenant_id', $tenantId)->where('uuid', $opportunity)->with('volunteers')->firstOrFail();

        $rows = [
            ['Roster: '.$model->title, $model->starts_on->toDateString().($model->location ? ' · '.$model->location : '')],
            ['Name', 'Phone', 'Email', 'Skills'],
            ...$model->volunteers->sortBy('name')->map(fn ($volunteer): array => [$volunteer->name, $volunteer->phone, $volunteer->email, $volunteer->skills])->values()->all(),
        ];
        $name = 'roster-'.Str::slug($model->title);

        return $request->query('format') === 'pdf' ? $pdf->download($tenantId, $name, $rows) : $csv->download($name.'.csv', $rows);
    }

    public function toggle(Request $request, string $opportunity): RedirectResponse
    {
        $tenantId = $this->allow($request, 'volunteers.manage');
        $model = VolOpportunity::query()->where('tenant_id', $tenantId)->where('uuid', $opportunity)->firstOrFail();
        $model->update(['status' => $model->status === 'open' ? 'closed' : 'open']);

        return back()->with('success', $model->status === 'open' ? 'Opportunity reopened.' : 'Opportunity closed.');
    }
}
