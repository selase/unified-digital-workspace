<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\CmsCore\Models\Post
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'post_type_id' => $this->post_type_id,
            'post_type' => $this->whenLoaded('postType', fn () => new PostTypeResource($this->postType)),
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'author_id' => $this->author_id,
            'editor_id' => $this->editor_id,
            'featured_media_id' => $this->featured_media_id,
            'featured_media' => $this->whenLoaded('featuredMedia', fn () => new MediaResource($this->featuredMedia)),
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'meta' => PostMetaResource::collection($this->whenLoaded('meta')),
            'revisions' => PostRevisionResource::collection($this->whenLoaded('revisions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
