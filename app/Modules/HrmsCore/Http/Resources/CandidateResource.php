<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\Candidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Candidate
 */
final class CandidateResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'current_employer' => $this->current_employer,
            'current_position' => $this->current_position,
            'current_salary' => $this->current_salary,
            'expected_salary' => $this->expected_salary,
            'notice_period' => $this->notice_period,
            'years_of_experience' => $this->years_of_experience,
            'highest_qualification' => $this->highest_qualification,
            'institution' => $this->institution,
            'graduation_year' => $this->graduation_year,
            'skills' => $this->skills,
            'languages' => $this->languages,
            'source' => $this->source,
            'referrer_name' => $this->referrer_name,
            'referrer_email' => $this->referrer_email,
            'status' => $this->status,
            'notes' => $this->notes,
            'applications' => CandidateApplicationResource::collection($this->whenLoaded('applications')),
            'documents' => CandidateDocumentResource::collection($this->whenLoaded('documents')),
            'references' => CandidateReferenceResource::collection($this->whenLoaded('references')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
