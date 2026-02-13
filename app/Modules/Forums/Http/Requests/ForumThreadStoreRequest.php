<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForumThreadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forums.post') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'body' => ['required', 'string'],
        ];
    }
}
