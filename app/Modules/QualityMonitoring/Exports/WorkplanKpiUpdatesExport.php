<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Exports;

use App\Modules\QualityMonitoring\Models\Activity;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\KpiUpdate;
use App\Modules\QualityMonitoring\Models\Objective;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class WorkplanKpiUpdatesExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly int $workplanId,
        private readonly array $filters = [],
    ) {}

    public function query(): Builder
    {
        $objectiveIds = Objective::query()
            ->where('workplan_id', $this->workplanId)
            ->select('id');

        $activityIds = Activity::query()
            ->whereIn('objective_id', $objectiveIds)
            ->select('id');

        $kpiIds = Kpi::query()
            ->whereIn('activity_id', $activityIds)
            ->select('id');

        $query = KpiUpdate::query()
            ->whereIn('kpi_id', $kpiIds)
            ->with(['kpi.activity.objective'])
            ->latest();

        $this->applyDateRange($query, 'created_at');

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'KPI',
            'Activity',
            'Objective',
            'Value',
            'Captured At',
            'Captured By',
            'Note',
            'Evidence Path',
            'Created At',
        ];
    }

    public function map($update): array
    {
        return [
            $update->id,
            $update->kpi?->name,
            $update->kpi?->activity?->title,
            $update->kpi?->activity?->objective?->title,
            $update->value,
            optional($update->captured_at)->format('Y-m-d H:i:s'),
            $update->captured_by_id,
            $update->note,
            $update->evidence_path,
            optional($update->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function applyDateRange(Builder $query, string $column): void
    {
        $from = $this->filters['from'] ?? null;
        $to = $this->filters['to'] ?? null;

        if ($from) {
            $query->where($column, '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where($column, '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
