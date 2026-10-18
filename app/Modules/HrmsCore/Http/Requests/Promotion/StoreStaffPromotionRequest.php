<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Promotion;

use App\Modules\HrmsCore\Enums\PromotionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStaffPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('hrms.promotion.request') ?? false)
            || ($user?->can('hrms.promotion.recommend') ?? false)
            || ($user?->can('hrms.promotion.approve') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'category' => [
                'required',
                Rule::enum(PromotionCategory::class),
            ],
            'to_grade_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_grades', 'id')->where('tenant_id', $tenantId),
            ],
            'to_salary_level_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_salary_levels', 'id')->where('tenant_id', $tenantId),
            ],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'effective_date' => ['nullable', 'date'],
            'requested_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'submit_for_review' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select the employee for this promotion request.',
            'employee_id.exists' => 'The selected employee is invalid for this organization.',
            'category.required' => 'Please select a promotion category.',
            'category.enum' => 'The selected promotion category is not valid.',
            'to_grade_id.exists' => 'The proposed grade is invalid for this organization.',
            'to_salary_level_id.exists' => 'The proposed salary level is invalid for this organization.',
            'supervisor_id.exists' => 'The selected reviewing supervisor is invalid for this organization.',
            'reason.required' => 'Please provide the reason for this promotion request.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
