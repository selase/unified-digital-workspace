<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\ProjectManagement\Models\TaskAssignment
 */
final class TaskAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'assigned_by_id' => $this->assigned_by_id,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
        ];
    }
}
