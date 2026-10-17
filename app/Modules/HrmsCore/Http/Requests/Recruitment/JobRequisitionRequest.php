<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\HrmsCore\Models\Organization\Grade;
use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class JobRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($this->isMethod('POST')) {
            return $user->can('hrms.jobs.create') || $user->can('hrms.jobs.manage');
        }

        return $user->can('hrms.jobs.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = (string) (session('active_tenant_id') ?? $user?->tenant_id);
        $requisition = $this->route('requisition');
        $requisitionId = $requisition instanceof JobRequisition ? $requisition->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'requisition_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique(JobRequisition::class, 'requisition_number')
                    ->where('tenant_id', $tenantId)
                    ->ignore($requisitionId),
            ],
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
            'requested_by' => [
                'nullable',
                'integer',
                Rule::exists(Employee::class, 'id')->where('tenant_id', $tenantId),
            ],
            'employment_type' => ['required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'temporary'])],
            'vacancies' => ['required', 'integer', 'min:1', 'max:500'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_remote' => ['nullable', 'boolean'],
            'target_start_date' => ['nullable', 'date'],
            'application_deadline' => ['nullable', 'date'],
            'job_description' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'responsibilities' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Job requisition title is required.',
            'requisition_number.unique' => 'This requisition number is already in use.',
            'department_id.exists' => 'The selected department does not exist in this organization.',
            'grade_id.exists' => 'The selected salary grade does not exist in this organization.',
            'employment_type.in' => 'Employment type must be full-time, part-time, contract, or temporary.',
            'vacancies.min' => 'Number of vacancies must be at least 1.',
            'max_salary.gte' => 'Maximum salary must be greater than or equal to minimum salary.',
        ];
    }
}
