<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Tag;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cms.posts.create');
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
                Rule::exists(PostType::class, 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(Post::class, 'slug')
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
                Rule::exists(Media::class, 'id')->where('tenant_id', $tenantId),
            ],
            'featured_image' => ['nullable', 'image', 'max:10240'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Post::class, 'id')->where('tenant_id', $tenantId),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists(Category::class, 'id')->where('tenant_id', $tenantId),
            ],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                Rule::exists(Tag::class, 'id')->where('tenant_id', $tenantId),
            ],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => [
                'integer',
                Rule::exists(Media::class, 'id')->where('tenant_id', $tenantId),
            ],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_canonical' => ['nullable', 'url', 'max:500'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'audio_url' => ['nullable', 'url', 'max:500'],
            'poster_media_id' => ['nullable', 'integer'],
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
