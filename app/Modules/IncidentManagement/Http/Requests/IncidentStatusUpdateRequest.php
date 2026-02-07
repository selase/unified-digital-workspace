<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use App\Modules\IncidentManagement\Models\IncidentStatus;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IncidentStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('incidents.statuses.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IncidentStatus $status */
        $status = $this->route('status');
        $tenantId = $this->tenantId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('incident_statuses', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($status->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_terminal' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
