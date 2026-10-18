<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmployeeAllowanceRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'employee_id' => [
                $isUpdate ? 'nullable' : 'required',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'allowance_id' => [
                $isUpdate ? 'nullable' : 'required',
                'integer',
                Rule::exists('hrms_allowances', 'id')->where('tenant_id', $tenantId),
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee is invalid for this organization.',
            'allowance_id.required' => 'Please select an allowance.',
            'allowance_id.exists' => 'The selected allowance is invalid for this organization.',
            'effective_from.required' => 'Please select the effective start date.',
            'effective_to.after_or_equal' => 'The effective end date must be on or after the start date.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
