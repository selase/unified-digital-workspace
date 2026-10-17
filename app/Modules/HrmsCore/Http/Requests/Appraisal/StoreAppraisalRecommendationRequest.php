<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use App\Modules\HrmsCore\Enums\RecommendationType;
use App\Modules\HrmsCore\Models\Appraisal\AppraisalRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppraisalRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('hrms.appraisal.evaluate') ?? false)
            || ($user?->can('hrms.appraisal.manage') ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'type' => ['required', Rule::enum(RecommendationType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'action_plan' => ['nullable', 'string', 'max:2000'],
            'target_date' => ['nullable', 'date'],
            'recommended_by' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => [
                'nullable',
                Rule::in(array_keys(AppraisalRecommendation::statuses())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select a recommendation type.',
            'type.enum' => 'The selected recommendation type is not valid.',
            'recommended_by.exists' => 'The selected recommending staff member is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
