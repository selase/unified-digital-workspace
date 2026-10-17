<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Dossier;

use Illuminate\Foundation\Http\FormRequest;

final class EducationRequest extends FormRequest
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
            'institution_name' => ['required', 'string', 'max:255'],
            'qualification' => ['required', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'grade' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_name.required' => 'Institution name is required.',
            'qualification.required' => 'Degree or certificate title is required.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
