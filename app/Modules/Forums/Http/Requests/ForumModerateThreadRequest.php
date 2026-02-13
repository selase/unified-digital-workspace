<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ForumModerateThreadRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(['pin', 'unpin', 'lock', 'unlock', 'flag', 'delete'])],
            'reason' => ['nullable', 'string'],
        ];
    }
}
