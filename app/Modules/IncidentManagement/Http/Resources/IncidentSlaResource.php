<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\IncidentManagement\Models\IncidentSla
 */
final class IncidentSlaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->incident_id,
            'response_due_at' => $this->response_due_at?->toIso8601String(),
            'resolution_due_at' => $this->resolution_due_at?->toIso8601String(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'resolution_at' => $this->resolution_at?->toIso8601String(),
            'is_breached' => $this->is_breached,
            'breached_at' => $this->breached_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
