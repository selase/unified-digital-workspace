<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Forums\Models\ForumModerationLog
 */
final class ForumModerationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'thread_id' => $this->thread_id,
            'post_id' => $this->post_id,
            'moderator_id' => $this->moderator_id,
            'action' => $this->action,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
