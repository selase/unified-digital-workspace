<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

final class SaveTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.attendance.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'max:70'],
            'entries.*.work_date' => ['required', 'date'],
            'entries.*.hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entries.*.project' => ['nullable', 'string', 'max:120'],
            'entries.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.*.hours.max' => 'A day cannot have more than 24 hours.',
            'entries.*.hours.numeric' => 'Hours must be a number, for example 7.5.',
        ];
    }
}
