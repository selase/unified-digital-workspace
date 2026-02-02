<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
final class LeaveRequestResource extends JsonResource
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
            'category' => $this->whenLoaded('leaveCategory', fn () => new LeaveCategoryResource($this->leaveCategory)),
            'status' => $this->status->value,
            'proposed_start_date' => $this->proposed_start_date->format('Y-m-d'),
            'proposed_end_date' => $this->proposed_end_date->format('Y-m-d'),
            'no_requested_days' => $this->no_requested_days,
            'leave_reasons' => $this->leave_reasons,
            'contact_when_away' => $this->contact_when_away,
            'no_recommended_days' => $this->no_recommended_days,
            'recommended_start_date' => $this->recommended_start_date?->format('Y-m-d'),
            'recommended_end_date' => $this->recommended_end_date?->format('Y-m-d'),
            'supervisor_comments' => $this->supervisor_comments,
            'supervisor_verified_at' => $this->supervisor_verified_at?->toIso8601String(),
            'hr_comments' => $this->hr_comments,
            'hr_verified_at' => $this->hr_verified_at?->toIso8601String(),
            'hr_verification_status' => $this->hr_verification_status,
            'no_of_days_approved' => $this->no_of_days_approved,
            'approved_start_date' => $this->approved_start_date?->format('Y-m-d'),
            'approved_end_date' => $this->approved_end_date?->format('Y-m-d'),
            'hod_comments' => $this->hod_comments,
            'hod_decision_at' => $this->hod_decision_at?->toIso8601String(),
            'resumption_date' => $this->resumption_date?->format('Y-m-d'),
            'no_of_holidays_in_period' => $this->no_of_holidays_in_period,
            'no_of_weekends_in_period' => $this->no_of_weekends_in_period,
            'is_recalled' => $this->is_recalled,
            'recall_date' => $this->recall_date?->format('Y-m-d'),
            'no_of_days_recalled' => $this->no_of_days_recalled,
            'recall_reason' => $this->recall_reason,
            'recalled_at' => $this->recalled_at?->toIso8601String(),
            'supervisor' => $this->whenLoaded('supervisor', fn () => new EmployeeResource($this->supervisor)),
            'hod' => $this->whenLoaded('hod', fn () => new EmployeeResource($this->hod)),
            'hr_verifier' => $this->whenLoaded('hrVerifier', fn () => new EmployeeResource($this->hrVerifier)),
            'relieving_officer' => $this->whenLoaded('relievingOfficer', fn () => new EmployeeResource($this->relievingOfficer)),
            'balance_at_request' => $this->balance_at_request,
            'carry_forward_at_request' => $this->carry_forward_at_request,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
