<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Exports;

use App\Modules\QualityMonitoring\Models\Variance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class WorkplanVariancesExport implements FromQuery, WithHeadings, WithMapping
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
        $query = Variance::query()
            ->where('workplan_id', $this->workplanId)
            ->with(['activity', 'kpi'])
            ->latest();

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (! empty($this->filters['category'])) {
            $query->where('category', $this->filters['category']);
        }

        if (! empty($this->filters['impact_level'])) {
            $query->where('impact_level', $this->filters['impact_level']);
        }

        $this->applyDateRange($query, 'created_at');

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Workplan ID',
            'Activity',
            'KPI',
            'Category',
            'Impact Level',
            'Status',
            'Revised Date',
            'Corrective Action',
            'Narrative',
            'Reviewed At',
            'Created At',
        ];
    }

    public function map($variance): array
    {
        return [
            $variance->id,
            $variance->workplan_id,
            $variance->activity?->title,
            $variance->kpi?->name,
            $variance->category,
            $variance->impact_level,
            $variance->status,
            optional($variance->revised_date)->format('Y-m-d'),
            $variance->corrective_action,
            $variance->narrative,
            optional($variance->reviewed_at)->format('Y-m-d H:i:s'),
            optional($variance->created_at)->format('Y-m-d H:i:s'),
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
