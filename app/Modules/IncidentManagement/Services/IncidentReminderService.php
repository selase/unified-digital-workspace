<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Services;

use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentReminder;
use Carbon\CarbonInterface;

final class IncidentReminderService
{
    public function generateUpcomingReminders(): int
    {
        $count = 0;

        $incidents = Incident::query()
            ->whereNull('closed_at')
            ->whereNull('resolved_at')
            ->with(['sla', 'priority'])
            ->get();

        foreach ($incidents as $incident) {
            $count += $this->scheduleDueReminder($incident, $incident->due_at);
            $count += $this->scheduleResponseReminder($incident);
            $count += $this->scheduleResolutionReminder($incident);
        }

        return $count;
    }

    public function scheduleDueReminder(Incident $incident, ?CarbonInterface $dueAt): int
    {
        if (! $dueAt) {
            return 0;
        }

        $scheduledFor = $dueAt->copy()->subDay();

        if ($scheduledFor->isPast()) {
            return 0;
        }

        if ($this->reminderExists($incident->id, 'due_soon', $scheduledFor)) {
            return 0;
        }

        IncidentReminder::create([
            'tenant_id' => $incident->tenant_id,
            'incident_id' => $incident->id,
            'reminder_type' => 'due_soon',
            'scheduled_for' => $scheduledFor,
            'channel' => 'email',
            'metadata' => [
                'user_id' => $incident->assigned_to_id,
                'incident_id' => $incident->id,
            ],
        ]);

        return 1;
    }

    public function scheduleResponseReminder(Incident $incident): int
    {
        if (! $incident->sla || ! $incident->sla->response_due_at) {
            return 0;
        }

        $leadMinutes = $this->leadTimeFromMinutes($incident->priority?->response_time_minutes ?? 30, 60, 5);
        $scheduledFor = $incident->sla->response_due_at->copy()->subMinutes($leadMinutes);

        if ($scheduledFor->isPast()) {
            return 0;
        }

        if ($this->reminderExists($incident->id, 'sla_response_due', $scheduledFor)) {
            return 0;
        }

        IncidentReminder::create([
            'tenant_id' => $incident->tenant_id,
            'incident_id' => $incident->id,
            'reminder_type' => 'sla_response_due',
            'scheduled_for' => $scheduledFor,
            'channel' => 'email',
            'metadata' => [
                'user_id' => $incident->assigned_to_id,
                'incident_id' => $incident->id,
            ],
        ]);

        return 1;
    }

    public function scheduleResolutionReminder(Incident $incident): int
    {
        if (! $incident->sla || ! $incident->sla->resolution_due_at) {
            return 0;
        }

        $leadMinutes = $this->leadTimeFromMinutes($incident->priority?->resolution_time_minutes ?? 60, 120, 10);
        $scheduledFor = $incident->sla->resolution_due_at->copy()->subMinutes($leadMinutes);

        if ($scheduledFor->isPast()) {
            return 0;
        }

        if ($this->reminderExists($incident->id, 'sla_resolution_due', $scheduledFor)) {
            return 0;
        }

        IncidentReminder::create([
            'tenant_id' => $incident->tenant_id,
            'incident_id' => $incident->id,
            'reminder_type' => 'sla_resolution_due',
            'scheduled_for' => $scheduledFor,
            'channel' => 'email',
            'metadata' => [
                'user_id' => $incident->assigned_to_id,
                'incident_id' => $incident->id,
            ],
        ]);

        return 1;
    }

    private function reminderExists(string $incidentId, string $type, CarbonInterface $scheduledFor): bool
    {
        return IncidentReminder::query()
            ->where('incident_id', $incidentId)
            ->where('reminder_type', $type)
            ->where('scheduled_for', $scheduledFor)
            ->exists();
    }

    private function leadTimeFromMinutes(int $totalMinutes, int $max, int $min): int
    {
        $calculated = (int) floor($totalMinutes * 0.2);

        return max($min, min($max, $calculated > 0 ? $calculated : $min));
    }
}
