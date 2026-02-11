<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Memos\Models\MemoAction
 */
final class MemoActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'assigned_to_id' => $this->assigned_to_id,
            'due_at' => $this->due_at,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
