<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('memberships.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'integer'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.required' => 'Enter the member\'s name.', 'type_id.required' => 'Choose a membership type.'];
    }
}
