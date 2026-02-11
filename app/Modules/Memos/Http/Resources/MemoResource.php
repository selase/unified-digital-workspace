<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Memos\Models\Memo
 */
final class MemoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'tenant_id' => $this->tenant_id,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'sender_id' => $this->sender_id,
            'signed_at' => $this->signed_at,
            'sent_at' => $this->sent_at,
            'signature' => $this->signature_path ? [
                'disk' => $this->signature_disk,
                'path' => $this->signature_path,
                'filename' => $this->signature_filename,
                'mime_type' => $this->signature_mime_type,
                'size_bytes' => $this->signature_size_bytes,
            ] : null,
            'recipients' => MemoRecipientResource::collection($this->whenLoaded('recipients')),
            'minutes' => MemoMinuteResource::collection($this->whenLoaded('minutes')),
            'actions' => MemoActionResource::collection($this->whenLoaded('actions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
