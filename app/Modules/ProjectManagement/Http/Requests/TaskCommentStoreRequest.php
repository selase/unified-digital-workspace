<?php

declare(strict_types=1);

namespace App\Modules\ProjectManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TaskCommentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projects.tasks.manage') ?? false;
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
