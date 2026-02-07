<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\CmsCore\Models\MediaVariant
 */
final class MediaVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'media_id' => $this->media_id,
            'variant' => $this->variant,
            'disk' => $this->disk,
            'path' => $this->path,
            'width' => $this->width,
            'height' => $this->height,
            'size_bytes' => $this->size_bytes,
            'mime_type' => $this->mime_type,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
