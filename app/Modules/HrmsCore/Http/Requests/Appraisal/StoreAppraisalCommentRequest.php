<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Appraisal;

use App\Modules\HrmsCore\Models\Appraisal\AppraisalComment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAppraisalCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'author_id' => [
                'nullable',
                'integer',
                Rule::exists('hrms_employees', 'id')->where('tenant_id', $tenantId),
            ],
            'comment' => ['required', 'string', 'max:2000'],
            'type' => [
                'required',
                Rule::in(array_keys(AppraisalComment::types())),
            ],
            'is_private' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required' => 'Please enter a comment before submitting.',
            'type.required' => 'Please select the comment type.',
            'type.in' => 'The selected comment type is not valid.',
            'author_id.exists' => 'The selected comment author is invalid for this organization.',
        ];
    }

    private function resolveTenantId(): string
    {
        return (string) (session('active_tenant_id') ?? $this->user()?->tenant_id);
    }
}
