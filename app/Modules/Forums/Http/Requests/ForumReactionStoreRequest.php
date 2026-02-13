<?php

declare(strict_types=1);

namespace App\Modules\Forums\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForumReactionStoreRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:40'],
        ];
    }
}
