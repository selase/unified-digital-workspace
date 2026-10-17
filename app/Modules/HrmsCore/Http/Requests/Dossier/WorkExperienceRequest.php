<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Dossier;

use Illuminate\Foundation\Http\FormRequest;

final class WorkExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('hrms.employees.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'responsibilities' => ['nullable', 'string'],
            'reason_for_leaving' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Employer / company name is required.',
            'position.required' => 'Job title / position is required.',
            'end_date.after_or_equal' => 'End date must be on or after the employment start date.',
        ];
    }
}
