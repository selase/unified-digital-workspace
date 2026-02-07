<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DocumentQuizStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.manage_quizzes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'questions' => ['nullable', 'array'],
            'questions.*.body' => ['required', 'string'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.correct_option' => ['nullable', 'string'],
            'questions.*.points' => ['nullable', 'integer', 'min:1'],
            'questions.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
