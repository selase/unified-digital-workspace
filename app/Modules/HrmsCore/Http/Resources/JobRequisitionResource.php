<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobRequisition
 */
final class JobRequisitionResource extends JsonResource
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
            'requisition_number' => $this->requisition_number,
            'title' => $this->title,
            'department' => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'grade' => $this->whenLoaded('grade', fn () => new GradeResource($this->grade)),
            'employment_type' => $this->employment_type,
            'vacancies' => $this->vacancies,
            'job_description' => $this->job_description,
            'requirements' => $this->requirements,
            'responsibilities' => $this->responsibilities,
            'min_salary' => $this->min_salary,
            'max_salary' => $this->max_salary,
            'location' => $this->location,
            'is_remote' => $this->is_remote,
            'status' => $this->status->value,
            'requested_by' => $this->whenLoaded('requestedBy', fn () => new EmployeeResource($this->requestedBy)),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => new EmployeeResource($this->approvedBy)),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'target_start_date' => $this->target_start_date?->format('Y-m-d'),
            'application_deadline' => $this->application_deadline?->format('Y-m-d'),
            'postings' => JobPostingResource::collection($this->whenLoaded('postings')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
