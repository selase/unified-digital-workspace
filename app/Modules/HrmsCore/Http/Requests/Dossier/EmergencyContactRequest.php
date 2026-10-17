<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests\Dossier;

use Illuminate\Foundation\Http\FormRequest;

final class EmergencyContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Emergency contact name is required.',
            'phone.required' => 'Emergency contact phone number is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
