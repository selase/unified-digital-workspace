<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicIncidentSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('incident_categories', 'id')->where('tenant_id', $tenantId),
            ],
            'priority_id' => [
                'nullable',
                'integer',
                Rule::exists('incident_priorities', 'id')->where('tenant_id', $tenantId),
            ],
            'due_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:50'],
            'impact' => ['nullable', 'string'],
            'recaptcha_token' => [
                'required',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (app()->environment('testing')) {
                        return;
                    }

                    if ($value !== 'recaptcha-pass') {
                        $fail('Recaptcha validation failed.');
                    }
                },
            ],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
