<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Memos\Models\MemoMinute
 */
final class MemoMinuteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_id' => $this->author_id,
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
