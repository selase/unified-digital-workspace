<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppraisalTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hrms.appraisal.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'period_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_appraisal_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a template name.',
            'period_id.exists' => 'The selected appraisal period is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
