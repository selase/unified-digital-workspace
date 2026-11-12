<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\ResultsFramework\Http\Requests\AddReadingRequest;
use App\Modules\ResultsFramework\Http\Requests\SaveIndicatorRequest;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\BreakdownService;
use App\Modules\ResultsFramework\Services\IndicatorService;
use App\Modules\ResultsFramework\Services\MeasureRegistry;
use App\Services\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IndicatorController extends Controller
{
    public function index(Request $request, IndicatorService $indicators, MeasureRegistry $measures, BreakdownService $breakdowns): View
    {
        $tenantId = $this->allow($request, 'results.view');
        $viewer = $request->user();
        $maySeeExact = $viewer->can('beneficiaries.view');
        $rows = ResultIndicator::query()->where('tenant_id', $tenantId)->orderByRaw("case status when 'active' then 0 else 1 end")->with(['programme', 'tenant'])->orderBy('programme_id')->orderBy('name')->get()
            ->map(fn (ResultIndicator $indicator): array => [
                'indicator' => $indicator,
                'progress' => $indicators->progress($indicator, $viewer),
                'hiddenBreakdowns' => ! $maySeeExact && in_array(null, $breakdowns->allForIndicator($indicator, $viewer), true),
            ]);

        return view('results-framework::indicators.index', [
            'rows' => $rows->groupBy(fn (array $row): string => $row['indicator']->programmeName())->sortKeys(),
            'programmes' => ResultProgramme::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'resultOptions' => ResultNode::query()->where('tenant_id', $tenantId)->orderBy('title')->get(),
            'counts' => $rows->where('indicator.status', 'active')->countBy(fn (array $row): string => $row['progress']['status']),
            'measures' => $request->user()->can('results.manage') ? $measures->definitions(Tenant::query()->findOrFail($tenantId)) : [],
        ]);
    }

    public function store(SaveIndicatorRequest $request, IndicatorService $indicators): RedirectResponse
    {
        try {
            $indicator = $indicators->create(
                $this->tenantId($request),
                (string) $request->validated('name'),
                (float) $request->validated('target'),
                Carbon::parse($request->validated('starts_on')),
                Carbon::parse($request->validated('ends_on')),
                $request->indicatorFields(),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('results-framework.indicators.show', $indicator->uuid)->with('success', 'Indicator created.');
    }

    public function edit(Request $request, MeasureRegistry $measures, string $indicator): View
    {
        $tenantId = $this->allow($request, 'results.manage');
        $model = ResultIndicator::query()->where('tenant_id', $tenantId)->where('uuid', $indicator)->firstOrFail();

        return view('results-framework::indicators.edit', [
            'indicator' => $model,
            'programmes' => ResultProgramme::query()->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'resultOptions' => ResultNode::query()->where('tenant_id', $tenantId)->orderBy('title')->get(),
            'measures' => $measures->definitions(Tenant::query()->findOrFail($tenantId)),
            'measureLabel' => $model->measure_key === null ? null : $measures->label($model->measure_key),
        ]);
    }

    public function update(SaveIndicatorRequest $request, IndicatorService $indicators, string $indicator): RedirectResponse
    {
        $model = ResultIndicator::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $indicator)->firstOrFail();

        try {
            $indicators->update($model, $request->indicatorFields($model->measure_key));
        } catch (DomainException $error) {
            return back()->withInput()->withErrors(['indicator' => $error->getMessage()]);
        }

        return redirect()->route('results-framework.indicators.show', $indicator)->with('success', 'Indicator updated.');
    }

    public function show(Request $request, IndicatorService $indicators, MeasureRegistry $measures, BreakdownService $breakdowns, string $indicator): View
    {
        $tenantId = $this->allow($request, 'results.view');
        $model = ResultIndicator::query()->where('tenant_id', $tenantId)->where('uuid', $indicator)->firstOrFail();

        return view('results-framework::indicators.show', [
            'indicator' => $model->load('programme'),
            'progress' => $indicators->progress($model, $request->user()),
            'breakdowns' => $breakdowns->allForIndicator($model, $request->user()),
            'readings' => $model->readings()->limit(50)->get(),
            'measureLabel' => $model->measure_key === null ? 'Enter readings by hand' : $measures->label($model->measure_key),
        ]);
    }

    public function reading(AddReadingRequest $request, IndicatorService $indicators, string $indicator): RedirectResponse
    {
        $model = ResultIndicator::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $indicator)->firstOrFail();

        try {
            $indicators->record($model, (float) $request->validated('value'), Carbon::parse($request->validated('recorded_on')), $request->validated('note'), $request->user()?->uuid ? (string) $request->user()->uuid : null, $request->validated('breakdowns', []));
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Reading recorded.');
    }

    public function toggle(Request $request, string $indicator): RedirectResponse
    {
        $tenantId = $this->allow($request, 'results.manage');
        $model = ResultIndicator::query()->where('tenant_id', $tenantId)->where('uuid', $indicator)->firstOrFail();
        $model->update(['status' => $model->status === 'active' ? 'archived' : 'active']);

        return back()->with('success', $model->status === 'active' ? 'Indicator reactivated.' : 'Indicator archived.');
    }

    /** The indicator report as CSV (default) or PDF, for funders and board packs. */
    public function report(Request $request, IndicatorService $indicators): Response
    {
        $tenantId = $this->allow($request, 'results.view');
        $rows = $indicators->reportRows($tenantId, $request->user());
        $name = 'indicator-report-'.today()->toDateString();

        $format = $request->query('format');
        if (in_array($format, ['print', 'pdf'], true)) {
            $report = ['rows' => $rows, 'organisation' => (string) Tenant::query()->whereKey($tenantId)->value('name')];
            if ($format === 'print') {
                return response()->view('results-framework::pdf.report', $report + ['print' => true]);
            }

            $output = Pdf::loadView('results-framework::pdf.report', $report)->setPaper('a4', 'landscape')->output();

            return response($output, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($cell): string => (string) ($cell ?? ''), $row), ',', '"', '');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function tenantId(Request $request): string
    {
        $tenant = $request->attributes->get('tenant');
        if ($tenant instanceof Tenant) {
            return (string) $tenant->id;
        }
        $active = app(TenantContext::class)->getTenant();
        if ($active instanceof Tenant) {
            return (string) $active->id;
        }
        $session = $request->session()->get('active_tenant_id');
        abort_if(! is_string($session) || $session === '', 403, 'No active organisation.');

        return $session;
    }

    private function allow(Request $request, string $permission): string
    {
        abort_unless($request->user()?->can($permission), 403);

        return $this->tenantId($request);
    }
}
