<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IncidentAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.assign');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to_id' => ['required', 'string'],
            'note' => ['nullable', 'string'],
        ];
    }
}
