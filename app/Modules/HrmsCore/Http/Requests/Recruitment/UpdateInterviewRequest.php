<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Enums\InterviewStatus;
use App\Modules\HrmsCore\Models\Recruitment\Interview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateInterviewRequest extends FormRequest
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
            'type' => [
                'nullable',
                'string',
                Rule::in([Interview::TYPE_IN_PERSON, Interview::TYPE_PHONE, Interview::TYPE_VIDEO]),
            ],
            'interview_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'status' => [
                'required',
                'string',
                Rule::in(array_map(static fn (InterviewStatus $case): string => $case->value, InterviewStatus::cases())),
            ],
            'feedback_summary' => ['nullable', 'string', 'max:5000'],
            'overall_rating' => ['nullable', 'string', 'max:50'],
            'is_recommended' => ['nullable', 'boolean'],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Interview status is required.',
            'status.in' => 'The selected interview status is invalid.',
            'cancellation_reason.required_if' => 'A cancellation reason is required when cancelling an interview.',
        ];
    }
}
