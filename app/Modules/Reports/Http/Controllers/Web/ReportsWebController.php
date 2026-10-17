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
        $user = $request->user();
        abort_if(! $user?->can('reports.view') && ! $user?->can('reports.manage'), 403);

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
        $user = $request->user();
        abort_if(! ($user?->can('reports.create') || $user?->can('reports.manage')), 403);

        $tenant = $request->attributes->get('tenant');
        $availableSources = $this->registry->getAvailableSources($user);

        return view('reports::create', compact('availableSources', 'tenant'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! ($user?->can('reports.create') || $user?->can('reports.manage')), 403);

        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

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

        if (! $this->registry->userCanAccessSource($user, $validated['data_source'])) {
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
            'created_by_user_id' => (string) $user->uuid,
        ]);

        return redirect()->route('reports.show', $report)
            ->with('status', 'success')
            ->with('message', "Report [{$report->name}] created successfully.");
    }

    public function show(Request $request, ReportDefinition $report): View
    {
        $user = $request->user();
        abort_if(! $user?->can('reports.view') && ! $user?->can('reports.manage'), 403);

        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $user->tenant_id;

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        // Privacy IDOR check: non-public reports are restricted to author or report managers
        $isOwner = $report->created_by_user_id === (string) $user->uuid;
        $isManager = (bool) $user->can('reports.manage');
        abort_if(! $report->is_public && ! $isOwner && ! $isManager, 403, 'This private report can only be viewed by its author or report managers.');

        try {
            $preview = $this->generator->runPreview($report, $user, $tenantId, 50);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        return view('reports::show', compact('report', 'preview', 'tenant'));
    }

    public function exportCsv(Request $request, ReportDefinition $report): StreamedResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('reports.view') && ! $user?->can('reports.manage'), 403);

        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $user->tenant_id;

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        // Privacy IDOR check: non-public reports are restricted to author or report managers
        $isOwner = $report->created_by_user_id === (string) $user->uuid;
        $isManager = (bool) $user->can('reports.manage');
        abort_if(! $report->is_public && ! $isOwner && ! $isManager, 403, 'This private report can only be exported by its author or report managers.');

        try {
            return $this->generator->streamCsv($report, $user, $tenantId);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function exportPdf(Request $request, ReportDefinition $report): \Illuminate\Http\Response
    {
        $user = $request->user();
        abort_if(! $user?->can('reports.view') && ! $user?->can('reports.manage'), 403);

        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $user->tenant_id;

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        // Privacy IDOR check: non-public reports are restricted to author or report managers
        $isOwner = $report->created_by_user_id === (string) $user->uuid;
        $isManager = (bool) $user->can('reports.manage');
        abort_if(! $report->is_public && ! $isOwner && ! $isManager, 403, 'This private report can only be exported by its author or report managers.');

        try {
            return $this->generator->generatePdf($report, $user, $tenantId);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function destroy(Request $request, ReportDefinition $report): RedirectResponse
    {
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $request->user()?->tenant_id;

        if ($report->tenant_id !== $tenantId) {
            abort(404, 'Report not found in this tenant.');
        }

        $isOwner = $report->created_by_user_id === (string) $user?->uuid;
        $isManager = (bool) ($user?->can('reports.manage') || $user?->can('reports.delete'));
        abort_if(! $isOwner && ! $isManager, 403, 'Unauthorized to delete this report.');

        $name = $report->name;
        $report->delete();

        return redirect()->route('reports.index')
            ->with('status', 'success')
            ->with('message', "Report [{$name}] has been deleted.");
    }
}
