<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentPriorityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.priorities.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IncidentPriority $priority */
        $priority = $this->route('priority');
        $tenantId = $this->tenantId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('incident_priorities', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($priority->id),
            ],
            'level' => ['sometimes', 'integer', 'min:0'],
            'response_time_minutes' => ['nullable', 'integer', 'min:0'],
            'resolution_time_minutes' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
