<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PostStoreRequest extends FormRequest
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
        $postTypeId = $this->input('post_type_id');

        return [
            'post_type_id' => [
                'required',
                'integer',
                Rule::exists('post_types', 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->where('post_type_id', $postTypeId),
            ],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'author_id' => ['required', 'string'],
            'editor_id' => ['nullable', 'string'],
            'featured_media_id' => [
                'nullable',
                'integer',
                Rule::exists('media', 'id')->where('tenant_id', $tenantId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('posts', 'id')->where('tenant_id', $tenantId),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('tenant_id', $tenantId),
            ],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => [
                'integer',
                Rule::exists('media', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
