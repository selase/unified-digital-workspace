<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('governance.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:64'],
            'meets_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'quorum' => ['nullable', 'integer', 'min:1', 'max:100'],
            'agenda' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['title.required' => 'Give the meeting a title.', 'meets_at.required' => 'Say when the meeting is.'];
    }
}
