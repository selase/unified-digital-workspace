<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\DocumentManagement\Models\Document
 */
final class DocumentResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'owner_id' => $this->owner_id,
            'category' => $this->category,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'current_version' => $this->whenLoaded('currentVersion', fn () => new DocumentVersionResource($this->currentVersion)),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'quizzes' => DocumentQuizResource::collection($this->whenLoaded('quizzes')),
        ];
    }
}
