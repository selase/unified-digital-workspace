<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Requests;

use App\Modules\DocumentManagement\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DocumentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(Document::STATUSES)],
            'visibility' => ['sometimes', 'nullable', 'array'],
            'visibility.users' => ['nullable', 'array'],
            'visibility.teams' => ['nullable', 'array'],
            'visibility.departments' => ['nullable', 'array'],
            'visibility.directorates' => ['nullable', 'array'],
            'visibility.tenant_wide' => ['nullable', 'boolean'],
            'visibility.is_private' => ['nullable', 'boolean'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
