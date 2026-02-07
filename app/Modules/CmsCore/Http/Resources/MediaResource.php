<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\CmsCore\Models\Media
 */
final class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'disk' => $this->disk,
            'path' => $this->path,
            'original_filename' => $this->original_filename,
            'filename' => $this->filename,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'checksum_sha256' => $this->checksum_sha256,
            'width' => $this->width,
            'height' => $this->height,
            'duration_seconds' => $this->duration_seconds,
            'bitrate' => $this->bitrate,
            'fps' => $this->fps,
            'dominant_color' => $this->dominant_color,
            'blurhash' => $this->blurhash,
            'metadata' => $this->metadata,
            'alt_text' => $this->alt_text,
            'caption' => $this->caption,
            'title' => $this->title,
            'description' => $this->description,
            'uploaded_by' => $this->uploaded_by,
            'source' => $this->source,
            'is_public' => $this->is_public,
            'variants' => MediaVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
