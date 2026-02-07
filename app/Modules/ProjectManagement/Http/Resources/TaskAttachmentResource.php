<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\ProjectManagement\Models\TaskAttachment
 */
final class TaskAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'disk' => $this->disk,
            'path' => $this->path,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by_id' => $this->uploaded_by_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
