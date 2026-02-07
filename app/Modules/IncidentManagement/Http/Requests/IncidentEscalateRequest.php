<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentEscalateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.escalate');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_priority_id' => ['required', 'integer', Rule::exists('incident_priorities', 'id')],
            'reason' => ['nullable', 'string'],
        ];
    }
}
