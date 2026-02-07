<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Setting;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SettingUpdateRequest extends FormRequest
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
        /** @var Setting $setting */
        $setting = $this->route('setting');
        $tenantId = $this->tenantId();

        return [
            'group' => ['nullable', 'string', 'max:255'],
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes'],
            'key_unique' => [
                Rule::unique('settings', 'key')
                    ->where('tenant_id', $tenantId)
                    ->where('group', $this->input('group', $setting->group))
                    ->ignore($setting->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key_unique' => $this->input('key', $this->route('setting')?->key),
        ]);
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
