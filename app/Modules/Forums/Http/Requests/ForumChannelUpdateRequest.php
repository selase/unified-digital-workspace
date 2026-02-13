<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForumChannelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forums.moderate') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'visibility' => ['sometimes', 'nullable', 'array'],
            'visibility.tenant_wide' => ['nullable', 'boolean'],
            'visibility.users' => ['nullable', 'array'],
            'visibility.users.*' => ['string'],
            'is_locked' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
