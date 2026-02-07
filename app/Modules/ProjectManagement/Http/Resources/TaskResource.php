<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\ProjectManagement\Models\Task
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'milestone_id' => $this->milestone_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'estimated_minutes' => $this->estimated_minutes,
            'sort_order' => $this->sort_order,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'children' => self::collection($this->whenLoaded('children')),
            'assignments' => TaskAssignmentResource::collection($this->whenLoaded('assignments')),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),
            'dependencies' => TaskDependencyResource::collection($this->whenLoaded('dependencies')),
            'dependents' => TaskDependencyResource::collection($this->whenLoaded('dependents')),
            'time_entries' => TimeEntryResource::collection($this->whenLoaded('timeEntries')),
        ];
    }
}
