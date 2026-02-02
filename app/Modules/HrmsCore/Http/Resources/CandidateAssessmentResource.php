<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\CandidateAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateAssessment
 */
final class CandidateAssessmentResource extends JsonResource
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
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'score' => $this->score,
            'max_score' => $this->max_score,
            'passing_score' => $this->passing_score,
            'is_passed' => $this->is_passed,
            'score_percentage' => $this->getScorePercentage(),
            'results' => $this->results,
            'feedback' => $this->feedback,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'evaluated_by' => $this->whenLoaded('evaluatedBy', fn () => new EmployeeResource($this->evaluatedBy)),
            'evaluated_at' => $this->evaluated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
