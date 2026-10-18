<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalaryStepRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'salary_level_id' => [
                'required',
                'integer',
                Rule::exists('hrms_salary_levels', 'id')->where('tenant_id', $tenantId),
            ],
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_grades', 'id')->where('tenant_id', $tenantId),
            ],
            'step_number' => ['required', 'integer', 'min:1'],
            'step_increment' => ['required', 'numeric', 'min:0'],
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
            'name.required' => 'Please enter a salary step name.',
            'salary_level_id.required' => 'Please select a salary level for this step.',
            'salary_level_id.exists' => 'The selected salary level is invalid for this organization.',
            'grade_id.exists' => 'The selected grade is invalid for this organization.',
            'step_number.required' => 'Please enter a step number.',
            'step_increment.required' => 'Please enter the step increment amount.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
