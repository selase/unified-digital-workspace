<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppraisalReviewRequest extends FormRequest
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
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'reviewer_type' => [
                'required',
                Rule::in(array_keys(AppraisalReview::reviewerTypes())),
            ],
            'overall_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:2000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:2000'],
            'general_comments' => ['nullable', 'string', 'max:2000'],
            'decision' => [
                'required',
                Rule::in(array_keys(AppraisalReview::decisions())),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reviewer_type.required' => 'Please select the review stage (Supervisor, HOD, or HR).',
            'reviewer_type.in' => 'The selected review stage is not valid.',
            'decision.required' => 'Please select a review decision.',
            'decision.in' => 'The selected review decision is not valid.',
            'reviewer_id.exists' => 'The selected reviewer is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
