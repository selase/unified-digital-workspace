<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppraisalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('hrms.appraisal.create') ?? false)
            || ($user?->can('hrms.appraisal.manage') ?? false);
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
            'period_id' => [
                'required',
                'integer',
                Rule::exists('hrms_appraisal_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'template_id' => [
                'required',
                'integer',
                Rule::exists('hrms_appraisal_templates', 'id')->where('tenant_id', $tenantId),
            ],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'hod_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'hr_reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select the employee to appraise.',
            'employee_id.exists' => 'The selected employee is invalid for this organization.',
            'period_id.required' => 'Please select the appraisal period.',
            'period_id.exists' => 'The selected appraisal period is invalid for this organization.',
            'template_id.required' => 'Please select an appraisal template.',
            'template_id.exists' => 'The selected appraisal template is invalid for this organization.',
            'supervisor_id.exists' => 'The selected supervisor is invalid for this organization.',
            'hod_id.exists' => 'The selected Head of Department is invalid for this organization.',
            'hr_reviewer_id.exists' => 'The selected HR reviewer is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
