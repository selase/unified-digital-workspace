<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Dossier;

use App\Modules\HrmsCore\Models\Employees\EmploymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EmploymentStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(array_keys(EmploymentStatus::statuses()))],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'reason' => ['nullable', 'string'],
            'is_current' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Employment status transition is required.',
            'status.in' => 'Selected employment status is invalid.',
            'effective_date.required' => 'Effective date is required.',
            'end_date.after_or_equal' => 'End date must be on or after the effective date.',
        ];
    }
}

