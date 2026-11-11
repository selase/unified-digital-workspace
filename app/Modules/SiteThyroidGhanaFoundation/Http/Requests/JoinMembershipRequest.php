<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JoinMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'type_id' => ['required', 'integer'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'We need an email to reach you.',
            'email.email' => 'Enter a valid email address.',
            'type_id.required' => 'Choose a membership.',
            'website.max' => 'We could not accept this form.',
        ];
    }
}
