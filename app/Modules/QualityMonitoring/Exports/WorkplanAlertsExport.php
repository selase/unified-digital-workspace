<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Exports;

use App\Modules\QualityMonitoring\Models\Alert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class WorkplanAlertsExport implements FromQuery, WithHeadings, WithMapping
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
        $query = Alert::query()
            ->where('workplan_id', $this->workplanId)
            ->with(['kpi.activity.objective'])
            ->latest();

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (! empty($this->filters['type'])) {
            $query->where('type', $this->filters['type']);
        }

        if (array_key_exists('escalation_level', $this->filters) && $this->filters['escalation_level'] !== null) {
            $query->where('escalation_level', (int) $this->filters['escalation_level']);
        }

        $this->applyDateRange($query, 'created_at');

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Type',
            'Status',
            'Escalation Level',
            'Workplan ID',
            'KPI',
            'Activity',
            'Objective',
            'Sent At',
            'Created At',
            'Metadata',
        ];
    }

    public function map($alert): array
    {
        return [
            $alert->id,
            $alert->type,
            $alert->status,
            $alert->escalation_level,
            $alert->workplan_id,
            $alert->kpi?->name,
            $alert->kpi?->activity?->title,
            $alert->kpi?->activity?->objective?->title,
            optional($alert->sent_at)->format('Y-m-d H:i:s'),
            optional($alert->created_at)->format('Y-m-d H:i:s'),
            json_encode($alert->metadata),
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
