<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Memos\Models\MemoRecipient
 */
final class MemoRecipientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            'role' => $this->role,
            'recipient' => $this->recipientSummary(),
            'requires_ack' => $this->requires_ack,
            'acknowledged_at' => $this->acknowledged_at,
            'acknowledged_by_id' => $this->acknowledged_by_id,
            'shared_by_id' => $this->shared_by_id,
            'shared_at' => $this->shared_at,
            'created_at' => $this->created_at,
        ];
    }
}
