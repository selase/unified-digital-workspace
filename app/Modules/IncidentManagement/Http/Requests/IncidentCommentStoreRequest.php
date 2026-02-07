<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IncidentCommentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.comments.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
