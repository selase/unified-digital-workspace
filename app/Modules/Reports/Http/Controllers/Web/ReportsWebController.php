<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Modules\Reports\Models\ReportDefinition;
use App\Modules\Reports\Services\ReportDataSourceRegistry;
use App\Modules\Reports\Services\ReportGeneratorService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportsWebController extends Controller
{
    public function __construct(
        private readonly ReportDataSourceRegistry $registry,
        private readonly ReportGeneratorService $generator
    ) {}

    public function index(Request $request): View
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        $reports = ReportDefinition::where('tenant_id', $tenantId)
            ->with(['creator:uuid,first_name,last_name,email'])
            ->withCount('executionLogs')
            ->latest()
            ->paginate(15);

        return view('reports::index', compact('reports', 'tenant'));
    }

    public function create(Request $request): View
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        $availableSources = $this->registry->getAvailableSources($user);

        return view('reports::create', compact('availableSources', 'tenant'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'data_source' => ['required', 'string'],
            'selected_columns' => ['required', 'array', 'min:1'],
            'selected_columns.*' => ['string'],
            'date_column' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        if (! $this->registry->isValidSource($validated['data_source'])) {
            return back()->withErrors(['data_source' => 'Invalid data source selected.'])->withInput();
        }

        if ($user && ! $this->registry->userCanAccessSource($user, $validated['data_source'])) {
            abort(403, 'You do not have permission to build reports on this data source.');
        }

        $filters = [];
        if (! empty($validated['from_date'])) {
            $filters['from_date'] = $validated['from_date'];
        }
        if (! empty($validated['to_date'])) {
            $filters['to_date'] = $validated['to_date'];
        }

        $report = ReportDefinition::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'data_source' => $validated['data_source'],
            'selected_columns' => $this->registry->filterAllowedColumns($validated['data_source'], $validated['selected_columns']),
            'date_column' => $validated['date_column'] ?? null,
            'filters' => $filters,
            'is_public' => $request->boolean('is_public'),
            'created_by_user_id' => $user?->uuid !== null ? (string) $user->uuid : null,
        ]);

        return redirect()->route('reports.show', $report)
            ->with('status', 'success')
            ->with('message', "Report [{$report->name}] created successfully.");
    }

    public function show(Request $request, ReportDefinition $report): View|RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;
        $user = $request->user();

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        try {
            $preview = $this->generator->runPreview($report, $user, $tenantId, 50);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        return view('reports::show', compact('report', 'preview', 'tenant'));
    }

    public function exportCsv(Request $request, ReportDefinition $report): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;
        $user = $request->user();

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        try {
            return $this->generator->streamCsv($report, $user, $tenantId);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function destroy(Request $request, ReportDefinition $report): RedirectResponse
    {
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        $name = $report->name;
        $report->delete();

        return redirect()->route('reports.index')
            ->with('status', 'success')
            ->with('message', "Report [{$name}] has been deleted.");
    }
}
