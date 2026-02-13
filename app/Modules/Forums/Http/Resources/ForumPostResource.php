<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Forums\Models\ForumPost
 */
final class ForumPostResource extends JsonResource
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
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'is_best_answer' => $this->is_best_answer,
            'edited_at' => $this->edited_at?->toIso8601String(),
            'reactions' => ForumReactionResource::collection($this->whenLoaded('reactions')),
            'replies' => self::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
