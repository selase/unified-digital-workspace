<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DocumentQuizAttemptStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'responses' => ['required', 'array'],
            'responses.*.question_id' => ['required', 'integer'],
            'responses.*.answer' => ['required', 'string'],
        ];
    }
}
