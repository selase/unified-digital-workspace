<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.timesheets.approve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'comment' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required_if' => 'Say why the timesheet is being returned.',
        ];
    }
}
