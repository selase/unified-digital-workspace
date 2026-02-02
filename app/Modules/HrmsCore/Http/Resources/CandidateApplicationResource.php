<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\CandidateApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateApplication
 */
final class CandidateApplicationResource extends JsonResource
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
            'candidate' => $this->whenLoaded('candidate', fn () => new CandidateResource($this->candidate)),
            'posting' => $this->whenLoaded('posting', fn () => new JobPostingResource($this->posting)),
            'application_number' => $this->application_number,
            'status' => $this->status->value,
            'stage' => $this->stage,
            'cover_letter' => $this->cover_letter,
            'offered_salary' => $this->offered_salary,
            'proposed_start_date' => $this->proposed_start_date?->format('Y-m-d'),
            'screened_by' => $this->screened_by,
            'screened_at' => $this->screened_at?->toIso8601String(),
            'is_recommended' => $this->is_recommended,
            'screening_notes' => $this->screening_notes,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'hired_at' => $this->hired_at?->toIso8601String(),
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
            'assessments' => CandidateAssessmentResource::collection($this->whenLoaded('assessments')),
            'offer' => $this->whenLoaded('offer', fn () => new JobOfferResource($this->offer)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
