<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Requests;

use App\Modules\DocumentManagement\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(Document::STATUSES)],
            'visibility' => ['nullable', 'array'],
            'visibility.users' => ['nullable', 'array'],
            'visibility.teams' => ['nullable', 'array'],
            'visibility.departments' => ['nullable', 'array'],
            'visibility.directorates' => ['nullable', 'array'],
            'visibility.tenant_wide' => ['nullable', 'boolean'],
            'visibility.is_private' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'metadata' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
