<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Models\Recruitment\JobRequisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class JobPostingRequest extends FormRequest
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

        return [
            'requisition_id' => [
                'required',
                'integer',
                Rule::exists(JobRequisition::class, 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'is_external' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'posted_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:posted_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'requisition_id.required' => 'Please select a job requisition for this posting.',
            'requisition_id.exists' => 'The selected job requisition does not exist in this organization.',
            'title.required' => 'Job posting title is required.',
            'closing_date.after_or_equal' => 'Closing date must be on or after the posted date.',
        ];
    }
}
