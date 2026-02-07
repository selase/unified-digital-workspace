<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Category;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CategoryUpdateRequest extends FormRequest
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
        /** @var Category $category */
        $category = $this->route('category');
        $tenantId = $this->tenantId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($category->id),
            ],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
