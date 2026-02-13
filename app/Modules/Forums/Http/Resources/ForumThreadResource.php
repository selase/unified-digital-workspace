<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Forums\Models\ForumThread
 */
final class ForumThreadResource extends JsonResource
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
            'channel_id' => $this->channel_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'pinned_at' => $this->pinned_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'posts' => ForumPostResource::collection($this->whenLoaded('posts')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
