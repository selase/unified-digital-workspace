<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Promotion\StaffPromotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffPromotion
 */
final class StaffPromotionResource extends JsonResource
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
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'from_grade' => $this->whenLoaded('fromGrade', fn () => new GradeResource($this->fromGrade)),
            'to_grade' => $this->whenLoaded('toGrade', fn () => new GradeResource($this->toGrade)),
            'from_salary_level' => $this->whenLoaded('fromSalaryLevel', fn () => new SalaryLevelResource($this->fromSalaryLevel)),
            'to_salary_level' => $this->whenLoaded('toSalaryLevel', fn () => new SalaryLevelResource($this->toSalaryLevel)),
            'category' => $this->category->value,
            'status' => $this->status->value,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'requested_date' => $this->requested_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'justification' => $this->justification,
            'supporting_documents' => $this->supporting_documents,
            'supervisor_approved' => $this->supervisor_approved,
            'supervisor_comments' => $this->supervisor_comments,
            'supervisor_reviewed_at' => $this->supervisor_reviewed_at?->toIso8601String(),
            'hr_approved' => $this->hr_approved,
            'hr_comments' => $this->hr_comments,
            'hr_reviewed_at' => $this->hr_reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'supervisor' => $this->whenLoaded('supervisor', fn () => new EmployeeResource($this->supervisor)),
            'hr_approver' => $this->whenLoaded('hrApprover', fn () => new EmployeeResource($this->hrApprover)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
