<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForumChannelStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'array'],
            'visibility.tenant_wide' => ['nullable', 'boolean'],
            'visibility.users' => ['nullable', 'array'],
            'visibility.users.*' => ['string'],
            'is_locked' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
