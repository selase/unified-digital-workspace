<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ForumMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forums.messages.send') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'recipient_user_ids' => ['required', 'array', 'min:1'],
            'recipient_user_ids.*' => ['required', 'string'],
            'visibility' => ['nullable', 'array'],
            'visibility.scope' => ['nullable', 'string', Rule::in(['direct', 'unit', 'department', 'directorate', 'organization'])],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
