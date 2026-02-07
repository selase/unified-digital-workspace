<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\CmsCore\Models\PostRevision
 */
final class PostRevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
