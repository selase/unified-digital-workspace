<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppraisalReview
 */
final class AppraisalReviewResource extends JsonResource
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
            'reviewer' => $this->whenLoaded('reviewer', fn () => new EmployeeResource($this->reviewer)),
            'reviewer_type' => $this->reviewer_type,
            'overall_rating' => $this->overall_rating,
            'strengths' => $this->strengths,
            'areas_for_improvement' => $this->areas_for_improvement,
            'general_comments' => $this->general_comments,
            'decision' => $this->decision,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
