<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Salary;

use App\Modules\HrmsCore\Models\Salary\Allowance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('hrms.allowances.manage') ?? false)
            || ($user?->can('hrms.salary.manage') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'allowance_type_id' => [
                'required',
                'integer',
                Rule::exists('hrms_allowance_types', 'id')->where('tenant_id', $tenantId),
            ],
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_grades', 'id')->where('tenant_id', $tenantId),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => [
                'required',
                Rule::in(array_keys(Allowance::frequencyOptions())),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter an allowance name.',
            'allowance_type_id.required' => 'Please select an allowance category.',
            'allowance_type_id.exists' => 'The selected allowance category is invalid for this organization.',
            'grade_id.exists' => 'The selected grade is invalid for this organization.',
            'amount.required' => 'Please enter the allowance amount.',
            'frequency.required' => 'Please select the payment frequency.',
            'frequency.in' => 'The selected payment frequency is not valid.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
