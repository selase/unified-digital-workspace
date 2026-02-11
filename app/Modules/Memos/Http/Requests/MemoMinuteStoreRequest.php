<?php

declare(strict_types=1);

namespace App\Modules\Memos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MemoMinuteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('memos.minute');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }
}
