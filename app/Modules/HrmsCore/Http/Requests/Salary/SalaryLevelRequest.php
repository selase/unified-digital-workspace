<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalaryLevelRequest extends FormRequest
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
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_grades', 'id')->where('tenant_id', $tenantId),
            ],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a salary level name.',
            'base_salary.required' => 'Please enter the base salary amount.',
            'base_salary.min' => 'Base salary cannot be negative.',
            'grade_id.exists' => 'The selected grade is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
