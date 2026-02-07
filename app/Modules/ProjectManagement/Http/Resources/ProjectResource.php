<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\ProjectManagement\Models\Project
 */
final class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'budget_amount' => $this->budget_amount,
            'currency' => $this->currency,
            'owner_id' => $this->owner_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'milestones' => MilestoneResource::collection($this->whenLoaded('milestones')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'members' => ProjectMemberResource::collection($this->whenLoaded('members')),
            'allocations' => ResourceAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
