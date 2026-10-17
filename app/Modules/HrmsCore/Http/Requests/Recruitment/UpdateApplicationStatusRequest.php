<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.applications.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(static fn (ApplicationStatus $case): string => $case->value, ApplicationStatus::cases())),
            ],
            'stage' => ['nullable', 'string', 'max:100'],
            'screening_notes' => ['nullable', 'string', 'max:3000'],
            'is_recommended' => ['nullable', 'boolean'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Application status is required.',
            'status.in' => 'The selected application status is invalid.',
            'rejection_reason.required_if' => 'A rejection reason is required when rejecting an application.',
        ];
    }
}
