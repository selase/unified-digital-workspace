<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobOffer
 */
final class JobOfferResource extends JsonResource
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
            'application' => $this->whenLoaded('application', fn () => new CandidateApplicationResource($this->application)),
            'offer_number' => $this->offer_number,
            'position_title' => $this->position_title,
            'department' => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'grade' => $this->whenLoaded('grade', fn () => new GradeResource($this->grade)),
            'salary_level' => $this->whenLoaded('salaryLevel', fn () => new SalaryLevelResource($this->salaryLevel)),
            'employment_type' => $this->employment_type,
            'offered_salary' => $this->offered_salary,
            'benefits' => $this->benefits,
            'additional_terms' => $this->additional_terms,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'offer_valid_until' => $this->offer_valid_until?->format('Y-m-d'),
            'status' => $this->status->value,
            'approved_by' => $this->whenLoaded('approvedBy', fn () => new EmployeeResource($this->approvedBy)),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'candidate_decision' => $this->candidate_decision,
            'candidate_feedback' => $this->candidate_feedback,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'negotiations' => OfferNegotiationResource::collection($this->whenLoaded('negotiations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
