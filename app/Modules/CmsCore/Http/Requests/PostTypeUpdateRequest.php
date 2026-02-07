<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\PostType;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PostTypeUpdateRequest extends FormRequest
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
        /** @var PostType $postType */
        $postType = $this->route('postType');
        $tenantId = $this->tenantId();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('post_types', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($postType->id),
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'supports' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
