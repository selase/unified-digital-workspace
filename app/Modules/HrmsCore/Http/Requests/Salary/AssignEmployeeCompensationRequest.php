<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignEmployeeCompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrms.salary.manage') ?? false;
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
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_grades', 'id')->where('tenant_id', $tenantId),
            ],
            'salary_level_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_salary_levels', 'id')->where('tenant_id', $tenantId),
            ],
            'salary_step_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_salary_steps', 'id')->where('tenant_id', $tenantId),
            ],
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
            'grade_id.exists' => 'The selected grade is invalid for this organization.',
            'salary_level_id.exists' => 'The selected salary level is invalid for this organization.',
            'salary_step_id.exists' => 'The selected salary step is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
