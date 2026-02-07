<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Post;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PostUpdateRequest extends FormRequest
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
        /** @var Post $post */
        $post = $this->route('post');
        $tenantId = $this->tenantId();
        $postTypeId = $this->input('post_type_id', $post->post_type_id);

        return [
            'post_type_id' => [
                'sometimes',
                'integer',
                Rule::exists('post_types', 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('posts', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->where('post_type_id', $postTypeId)
                    ->ignore($post->id),
            ],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published', 'scheduled', 'archived'])],
            'excerpt' => ['nullable', 'string'],
            'body' => ['sometimes', 'string'],
            'published_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'author_id' => ['sometimes', 'string'],
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
