<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\OnboardingTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OnboardingTask
 */
final class OnboardingTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'application' => $this->whenLoaded('application', fn () => new CandidateApplicationResource($this->application)),
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => new EmployeeResource($this->assignedTo)),
            'completed_by' => $this->whenLoaded('completedBy', fn () => new EmployeeResource($this->completedBy)),
            'notes' => $this->notes,
            'sequence' => $this->sequence,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
