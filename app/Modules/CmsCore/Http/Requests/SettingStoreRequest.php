<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SettingStoreRequest extends FormRequest
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
            'group' => ['nullable', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required'],
            'key_unique' => [
                Rule::unique('settings', 'key')
                    ->where('tenant_id', $tenantId)
                    ->where('group', $this->input('group')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key_unique' => $this->input('key'),
        ]);
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
