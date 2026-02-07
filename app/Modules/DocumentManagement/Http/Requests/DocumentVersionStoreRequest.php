<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DocumentVersionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.manage_versions') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
