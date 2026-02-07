<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Menu;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MenuUpdateRequest extends FormRequest
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
        /** @var Menu $menu */
        $menu = $this->route('menu');
        $tenantId = $this->tenantId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('menus', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($menu->id),
            ],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
