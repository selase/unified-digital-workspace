<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Spatie\Activitylog\Models\Activity;

final class ActivityLogExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(private ?string $tenantId = null) {}

    public function query()
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest();

        if ($this->tenantId) {
            // Assuming activity logs have tenant_id or we filter by causer's tenant
            // Based on earlier migrations, we might have tenant_id on activity_log
            $query->where('properties->tenant_id', $this->tenantId);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Date',
            'Log Name',
            'Description',
            'Subject Type',
            'Subject ID',
            'Causer',
            'Properties',
        ];
    }

    public function map($activity): array
    {
        return [
            $activity->id,
            $activity->created_at->format('Y-m-d H:i:s'),
            $activity->log_name,
            $activity->description,
            $activity->subject_type,
            $activity->subject_id,
            $activity->causer?->displayName() ?? 'System',
            json_encode($activity->properties),
        ];
    }
}
