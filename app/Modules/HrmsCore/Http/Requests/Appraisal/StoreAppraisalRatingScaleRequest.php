<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppraisalRatingScaleRequest extends FormRequest
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
            'period_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_appraisal_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'value' => ['required', 'integer', 'min:1', 'max:5'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'Please specify a numeric rating value between 1 and 5.',
            'value.min' => 'Rating scale values must be at least 1.',
            'value.max' => 'Rating scale values cannot exceed 5.',
            'label.required' => 'Please provide a descriptive label for the rating level.',
            'period_id.exists' => 'The selected appraisal period is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
