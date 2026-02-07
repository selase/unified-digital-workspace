<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\IncidentManagement\Models\IncidentComment
 */
final class IncidentCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->incident_id,
            'user_id' => $this->user_id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
