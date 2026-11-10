<?php

declare(strict_types=1);

namespace App\Modules\Governance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveActionRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['description.required' => 'Describe what has to be done.'];
    }
}
