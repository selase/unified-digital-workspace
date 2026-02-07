<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\DocumentManagement\Models\DocumentQuizQuestion
 */
final class DocumentQuizQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'body' => $this->body,
            'options' => $this->options,
            'correct_option' => $this->correct_option,
            'points' => $this->points,
            'sort_order' => $this->sort_order,
        ];
    }
}
