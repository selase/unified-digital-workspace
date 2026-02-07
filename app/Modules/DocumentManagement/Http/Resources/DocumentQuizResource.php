<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\DocumentManagement\Models\DocumentQuiz
 */
final class DocumentQuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'title' => $this->title,
            'description' => $this->description,
            'settings' => $this->settings,
            'questions' => DocumentQuizQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
