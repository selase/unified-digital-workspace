<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\IncidentManagement\Models\IncidentTask
 */
final class IncidentTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->incident_id,
            'assigned_to_id' => $this->assigned_to_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
