<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppraisalRecommendation
 */
final class AppraisalRecommendationResource extends JsonResource
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
            'type' => $this->type->value,
            'description' => $this->description,
            'action_plan' => $this->action_plan,
            'target_date' => $this->target_date?->format('Y-m-d'),
            'status' => $this->status,
            'recommended_by' => $this->whenLoaded('recommendedBy', fn () => new EmployeeResource($this->recommendedBy)),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => new EmployeeResource($this->approvedBy)),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
