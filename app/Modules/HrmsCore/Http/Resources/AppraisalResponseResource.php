<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppraisalResponse
 */
final class AppraisalResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'criterion' => $this->whenLoaded('criterion', fn () => new AppraisalCriterionResource($this->criterion)),
            'self_rating' => $this->self_rating,
            'self_comments' => $this->self_comments,
            'supervisor_rating' => $this->supervisor_rating,
            'supervisor_comments' => $this->supervisor_comments,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
