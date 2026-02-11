<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MemoActionUpdateRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to_id' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['open', 'in_progress', 'done'])],
        ];
    }
}
