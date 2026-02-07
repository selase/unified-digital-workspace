<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\IncidentManagement\Models\Incident
 */
final class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_code' => $this->reference_code,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'priority_id' => $this->priority_id,
            'status_id' => $this->status_id,
            'reported_by_id' => $this->reported_by_id,
            'reporter_id' => $this->reporter_id,
            'reported_via' => $this->reported_via,
            'assigned_to_id' => $this->assigned_to_id,
            'due_at' => $this->due_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'source' => $this->source,
            'metadata' => $this->metadata,
            'impact' => $this->impact,
            'category' => $this->whenLoaded('category', fn () => new IncidentCategoryResource($this->category)),
            'priority' => $this->whenLoaded('priority', fn () => new IncidentPriorityResource($this->priority)),
            'status' => $this->whenLoaded('status', fn () => new IncidentStatusResource($this->status)),
            'reporter' => $this->whenLoaded('reporter', fn () => new IncidentReporterResource($this->reporter)),
            'tasks' => IncidentTaskResource::collection($this->whenLoaded('tasks')),
            'comments' => IncidentCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => IncidentAttachmentResource::collection($this->whenLoaded('attachments')),
            'escalations' => IncidentEscalationResource::collection($this->whenLoaded('escalations')),
            'reminders' => IncidentReminderResource::collection($this->whenLoaded('reminders')),
            'sla' => $this->whenLoaded('sla', fn () => new IncidentSlaResource($this->sla)),
            'progress_reports' => IncidentProgressReportResource::collection($this->whenLoaded('progressReports')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
