<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Forums\Models\ForumChannel
 */
final class ForumChannelResource extends JsonResource
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
            'visibility' => $this->visibility,
            'is_locked' => $this->is_locked,
            'sort_order' => $this->sort_order,
            'threads_count' => $this->whenCounted('threads'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
