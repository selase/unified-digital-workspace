<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublicApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'current_employer' => ['nullable', 'string', 'max:150'],
            'current_position' => ['nullable', 'string', 'max:150'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'highest_qualification' => ['nullable', 'string', 'max:150'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'website' => ['nullable', 'string', 'max:0'],
            'form_token' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Enter your first name.',
            'last_name.required' => 'Enter your last name.',
            'email.required' => 'Enter your email address so we can reach you.',
            'email.email' => 'Enter a valid email address.',
            'cv.mimes' => 'Attach your CV as a PDF or Word file.',
            'cv.max' => 'Your CV must be 5 MB or less.',
            'form_token.required' => 'This form has expired. Please reload the page and try again.',
        ];
    }
}
