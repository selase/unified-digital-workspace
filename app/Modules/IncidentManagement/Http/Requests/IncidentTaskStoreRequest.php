<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IncidentTaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.tasks.manage');
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
            'status' => ['sometimes', 'string', 'max:50'],
            'due_at' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
