<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('beneficiaries.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string'],
            'file' => ['required', 'file', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to attach.',
            'file.max' => 'The file must be 5 MB or less.',
        ];
    }
}
