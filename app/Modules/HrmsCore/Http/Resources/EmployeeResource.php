<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Resources;

use App\Modules\HrmsCore\Models\Employees\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
final class EmployeeResource extends JsonResource
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
            'staff_id' => $this->employee_staff_id,
            'cagd_staff_id' => $this->cagd_staff_id,
            'file_number' => $this->file_number,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'maiden_name' => $this->maiden_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'mobile' => $this->mobile,
            'home_phone' => $this->home_phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender?->value,
            'marital_status' => $this->marital_status?->value,
            'nationality' => $this->nationality,
            'postal_address' => $this->postal_address,
            'residential_address' => $this->residential_address,
            'town' => $this->town,
            'region' => $this->region,
            'gps_postcode' => $this->gps_postcode,
            'is_any_disability' => $this->is_any_disability,
            'disability_details' => $this->disability_details,
            'name_of_spouse' => $this->name_of_spouse,
            'spouse_phone_number' => $this->spouse_phone_number,
            'is_any_children' => $this->is_any_children,
            'number_of_children' => $this->number_of_children,
            'social_security_number' => $this->social_security_number,
            'profile_photo_path' => $this->profile_photo_path,
            'is_active' => $this->is_active,
            'grade' => $this->whenLoaded('grade', fn () => new GradeResource($this->grade)),
            'current_job' => $this->whenLoaded('currentJob', fn () => new CurrentJobResource($this->currentJob)),
            'departments' => DepartmentResource::collection($this->whenLoaded('departments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
