<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Dossier;

use Illuminate\Foundation\Http\FormRequest;

final class QualificationRequest extends FormRequest
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
            'certification_name' => ['required', 'string', 'max:255'],
            'issuing_body' => ['required', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'date_obtained' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'certification_name.required' => 'Certification name is required.',
            'issuing_body.required' => 'Issuing body/authority is required.',
        ];
    }
}
