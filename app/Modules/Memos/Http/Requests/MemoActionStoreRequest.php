<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MemoActionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('memos.actions.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_id' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
