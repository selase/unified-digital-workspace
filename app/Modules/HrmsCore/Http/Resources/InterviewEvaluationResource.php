<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\InterviewEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InterviewEvaluation
 */
final class InterviewEvaluationResource extends JsonResource
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
            'evaluator' => $this->whenLoaded('evaluator', fn () => new EmployeeResource($this->evaluator)),
            'criteria_scores' => $this->criteria_scores,
            'overall_score' => $this->overall_score,
            'overall_rating' => $this->overall_rating,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'comments' => $this->comments,
            'is_recommended' => $this->is_recommended,
            'recommendation_notes' => $this->recommendation_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
