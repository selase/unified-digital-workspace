<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentResolveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status_id' => ['nullable', 'integer', Rule::exists('incident_statuses', 'id')],
            'resolved_at' => ['nullable', 'date'],
        ];
    }
}
