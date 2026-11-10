<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('governance.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'position' => ['nullable', 'string', 'max:64'],
            'body' => ['nullable', 'string', 'max:64'],
            'term_starts_on' => ['nullable', 'date'],
            'term_ends_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => 'Enter the member\'s name.'];
    }
}
