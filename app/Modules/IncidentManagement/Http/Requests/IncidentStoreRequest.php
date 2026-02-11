<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use App\Modules\IncidentManagement\Models\IncidentCategory;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists(IncidentCategory::class, 'id')->where('tenant_id', $tenantId),
            ],
            'priority_id' => [
                'nullable',
                'integer',
                Rule::exists(IncidentPriority::class, 'id')->where('tenant_id', $tenantId),
            ],
            'status_id' => [
                'nullable',
                'integer',
                Rule::exists(IncidentStatus::class, 'id')->where('tenant_id', $tenantId),
            ],
            'assigned_to_id' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
            'impact' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
