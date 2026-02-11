<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Exports\WorkplanAlertsExport;
use App\Modules\QualityMonitoring\Exports\WorkplanKpiUpdatesExport;
use App\Modules\QualityMonitoring\Exports\WorkplanVariancesExport;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanExportRequest;
use App\Modules\QualityMonitoring\Http\Requests\WorkplanReportRequest;
use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Alert;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\KpiUpdate;
use App\Modules\QualityMonitoring\Models\Objective;
use App\Modules\QualityMonitoring\Models\Variance;
use App\Modules\QualityMonitoring\Models\Workplan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class WorkplanReportController extends Controller
{
    public function summary(WorkplanReportRequest $request, Workplan $workplan): JsonResponse
    {
        $objectiveIds = Objective::query()
            ->where('workplan_id', $workplan->id)
            ->select('id');

        $activityIds = Activity::query()
            ->whereIn('objective_id', $objectiveIds)
            ->select('id');

        $kpiIds = Kpi::query()
            ->whereIn('activity_id', $activityIds)
            ->select('id');

        $activityStatus = Activity::query()
            ->whereIn('id', $activityIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $kpiUpdatesQuery = KpiUpdate::query()->whereIn('kpi_id', $kpiIds);
        $variancesQuery = Variance::query()->where('workplan_id', $workplan->id);
        $alertsQuery = Alert::query()->where('workplan_id', $workplan->id);

        $this->applyDateRange($kpiUpdatesQuery, $request, 'created_at');
        $this->applyDateRange($variancesQuery, $request, 'created_at');
        $this->applyDateRange($alertsQuery, $request, 'created_at');

        return response()->json([
            'workplan_id' => $workplan->id,
            'objectives' => Objective::query()->where('workplan_id', $workplan->id)->count(),
            'activities' => Activity::query()->whereIn('id', $activityIds)->count(),
            'kpis' => Kpi::query()->whereIn('id', $kpiIds)->count(),
            'kpi_updates' => $kpiUpdatesQuery->count(),
            'variances' => $variancesQuery->count(),
            'alerts' => [
                'total' => $alertsQuery->count(),
                'open' => (clone $alertsQuery)->where('status', 'open')->count(),
                'acknowledged' => (clone $alertsQuery)->where('status', 'acknowledged')->count(),
                'escalated' => (clone $alertsQuery)->where('escalation_level', '>', 0)->count(),
            ],
            'activity_status' => $activityStatus,
        ]);
    }

    public function variances(WorkplanReportRequest $request, Workplan $workplan): JsonResponse
    {
        $query = Variance::query()
            ->where('workplan_id', $workplan->id)
            ->with(['activity', 'kpi']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('impact_level')) {
            $query->where('impact_level', $request->input('impact_level'));
        }

        $this->applyDateRange($query, $request, 'created_at');

        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function alerts(WorkplanReportRequest $request, Workplan $workplan): JsonResponse
    {
        $query = Alert::query()
            ->where('workplan_id', $workplan->id)
            ->with(['kpi.activity.objective']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('escalation_level')) {
            $query->where('escalation_level', $request->integer('escalation_level'));
        }

        $this->applyDateRange($query, $request, 'created_at');

        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function kpiUpdates(WorkplanReportRequest $request, Workplan $workplan): JsonResponse
    {
        $objectiveIds = Objective::query()
            ->where('workplan_id', $workplan->id)
            ->select('id');

        $activityIds = Activity::query()
            ->whereIn('objective_id', $objectiveIds)
            ->select('id');

        $kpiIds = Kpi::query()
            ->whereIn('activity_id', $activityIds)
            ->select('id');

        $query = KpiUpdate::query()
            ->whereIn('kpi_id', $kpiIds)
            ->with(['kpi.activity.objective']);

        $this->applyDateRange($query, $request, 'created_at');

        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function exportVariances(WorkplanExportRequest $request, Workplan $workplan): BinaryFileResponse
    {
        $export = new WorkplanVariancesExport($workplan->id, $request->validated());
        $filename = "workplan-variances-{$workplan->id}.{$this->exportExtension($request)}";

        return $export->download($filename, $this->exportFormat($request));
    }

    public function exportAlerts(WorkplanExportRequest $request, Workplan $workplan): BinaryFileResponse
    {
        $export = new WorkplanAlertsExport($workplan->id, $request->validated());
        $filename = "workplan-alerts-{$workplan->id}.{$this->exportExtension($request)}";

        return $export->download($filename, $this->exportFormat($request));
    }

    public function exportKpiUpdates(WorkplanExportRequest $request, Workplan $workplan): BinaryFileResponse
    {
        $export = new WorkplanKpiUpdatesExport($workplan->id, $request->validated());
        $filename = "workplan-kpi-updates-{$workplan->id}.{$this->exportExtension($request)}";

        return $export->download($filename, $this->exportFormat($request));
    }

    private function applyDateRange(Builder $query, WorkplanReportRequest $request, string $column): void
    {
        if ($request->filled('from')) {
            $query->whereDate($column, '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate($column, '<=', $request->date('to'));
        }
    }

    private function exportFormat(WorkplanExportRequest $request): string
    {
        return $request->input('format') === 'csv' ? Excel::CSV : Excel::XLSX;
    }

    private function exportExtension(WorkplanExportRequest $request): string
    {
        return $request->input('format') === 'csv' ? 'csv' : 'xlsx';
    }
}
