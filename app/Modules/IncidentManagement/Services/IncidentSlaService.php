<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Services;

use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentSla;

final class IncidentSlaService
{
    public function createOrUpdate(Incident $incident): IncidentSla
    {
        $priority = $incident->priority;
        $responseDueAt = null;
        $resolutionDueAt = null;

        if ($priority) {
            if ($priority->response_time_minutes !== null) {
                $responseDueAt = $incident->created_at?->copy()->addMinutes($priority->response_time_minutes);
            }

            if ($priority->resolution_time_minutes !== null) {
                $resolutionDueAt = $incident->created_at?->copy()->addMinutes($priority->resolution_time_minutes);
            }
        }

        return IncidentSla::updateOrCreate(
            ['incident_id' => $incident->id],
            [
                'response_due_at' => $responseDueAt,
                'resolution_due_at' => $resolutionDueAt,
            ]
        );
    }

    public function markResolved(Incident $incident): void
    {
        if (! $incident->sla) {
            return;
        }

        $incident->sla->fill([
            'resolution_at' => $incident->resolved_at ?? now(),
        ]);

        $incident->sla->save();
    }
}
