<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreJobOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.applications.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = (string) (session('active_tenant_id') ?? $user?->tenant_id);

        return [
            'position_title' => ['required', 'string', 'max:255'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists(Department::class, 'id')->where('tenant_id', $tenantId),
            ],
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists(Grade::class, 'id')->where('tenant_id', $tenantId),
            ],
            'employment_type' => ['required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'temporary'])],
            'offered_salary' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'offer_valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'additional_terms' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position_title.required' => 'Offer position title is required.',
            'offered_salary.required' => 'Offered salary amount is required.',
            'offered_salary.numeric' => 'Offered salary must be a valid amount.',
            'start_date.required' => 'Proposed start date is required.',
            'offer_valid_until.after_or_equal' => 'Offer expiration date must be today or in the future.',
        ];
    }
}
