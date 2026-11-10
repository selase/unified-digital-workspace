<?php

declare(strict_types=1);

namespace App\Modules\MonitoringEvaluation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\MonitoringEvaluation\Http\Requests\AddReadingRequest;
use App\Modules\MonitoringEvaluation\Http\Requests\SaveIndicatorRequest;
use App\Modules\MonitoringEvaluation\Models\MelIndicator;
use App\Modules\MonitoringEvaluation\Services\IndicatorService;
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
    public function index(Request $request, IndicatorService $indicators): View
    {
        $tenantId = $this->allow($request, 'monitoring.view');
        $rows = MelIndicator::query()->where('tenant_id', $tenantId)->orderByRaw("case status when 'active' then 0 else 1 end")->orderBy('programme')->orderBy('name')->get()
            ->map(fn (MelIndicator $indicator): array => ['indicator' => $indicator, 'progress' => $indicators->progress($indicator)]);

        return view('monitoring-evaluation::indicators.index', [
            'rows' => $rows,
            'counts' => $rows->where('indicator.status', 'active')->countBy(fn (array $row): string => $row['progress']['status']),
            'sources' => MelIndicator::SOURCES,
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
                $request->safe()->only(['description', 'programme', 'unit', 'source', 'baseline']),
            );
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('monitoring-evaluation.indicators.show', $indicator->uuid)->with('success', 'Indicator created.');
    }

    public function show(Request $request, IndicatorService $indicators, string $indicator): View
    {
        $tenantId = $this->allow($request, 'monitoring.view');
        $model = MelIndicator::query()->where('tenant_id', $tenantId)->where('uuid', $indicator)->firstOrFail();

        return view('monitoring-evaluation::indicators.show', [
            'indicator' => $model,
            'progress' => $indicators->progress($model),
            'readings' => $model->readings()->limit(50)->get(),
            'sources' => MelIndicator::SOURCES,
        ]);
    }

    public function reading(AddReadingRequest $request, IndicatorService $indicators, string $indicator): RedirectResponse
    {
        $model = MelIndicator::query()->where('tenant_id', $this->tenantId($request))->where('uuid', $indicator)->firstOrFail();

        try {
            $indicators->record($model, (float) $request->validated('value'), Carbon::parse($request->validated('recorded_on')), $request->validated('note'), $request->user()?->uuid ? (string) $request->user()->uuid : null);
        } catch (DomainException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return back()->with('success', 'Reading recorded.');
    }

    public function toggle(Request $request, string $indicator): RedirectResponse
    {
        $tenantId = $this->allow($request, 'monitoring.manage');
        $model = MelIndicator::query()->where('tenant_id', $tenantId)->where('uuid', $indicator)->firstOrFail();
        $model->update(['status' => $model->status === 'active' ? 'archived' : 'active']);

        return back()->with('success', $model->status === 'active' ? 'Indicator reactivated.' : 'Indicator archived.');
    }

    /** The indicator report as CSV (default) or PDF, for funders and board packs. */
    public function report(Request $request, IndicatorService $indicators): Response
    {
        $tenantId = $this->allow($request, 'monitoring.view');
        $rows = $indicators->reportRows($tenantId);
        $name = 'indicator-report-'.today()->toDateString();

        if ($request->query('format') === 'pdf') {
            $output = Pdf::loadView('monitoring-evaluation::pdf.report', ['rows' => $rows, 'organisation' => (string) Tenant::query()->whereKey($tenantId)->value('name')])->setPaper('a4', 'landscape')->output();

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
