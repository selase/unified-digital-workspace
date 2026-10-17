<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Recruitment;

use App\Modules\HrmsCore\Models\Recruitment\Interview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInterviewRequest extends FormRequest
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
                'required',
                'string',
                Rule::in([Interview::TYPE_IN_PERSON, Interview::TYPE_PHONE, Interview::TYPE_VIDEO]),
            ],
            'interview_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Interview format is required.',
            'type.in' => 'Interview format must be in-person, phone, or video.',
            'interview_date.required' => 'Interview date is required.',
            'start_time.required' => 'Interview start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.after' => 'End time must be after the start time.',
            'meeting_link.url' => 'Meeting link must be a valid URL.',
        ];
    }
}
