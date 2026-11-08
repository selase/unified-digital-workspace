<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

final class ManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.attendance.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'clock_in_at' => ['required', 'date'],
            'clock_out_at' => ['required', 'date', 'after:clock_in_at'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Choose an employee.',
            'clock_out_at.after' => 'The clock-out time must be after the clock-in time.',
        ];
    }
}
