<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Requests;

use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Menu;
use App\Modules\CmsCore\Models\MenuItem;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Tag;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PostUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cms.posts.update');
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
                Rule::exists(PostType::class, 'id')->where('tenant_id', $tenantId),
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique(Post::class, 'slug')
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
                Rule::exists(Media::class, 'id')->where('tenant_id', $tenantId),
            ],
            'featured_image' => ['nullable', 'image', 'max:10240'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Post::class, 'id')->where('tenant_id', $tenantId),
            ],
            'sort_order' => ['sometimes', 'integer', 'between:-100000,100000'],
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
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'required_with:cta_label', 'string', 'max:500', 'regex:/^(\/(?!\/)|https?:\/\/)\S*$/'],
            'cta2_label' => ['nullable', 'string', 'max:60'],
            'cta2_url' => ['nullable', 'required_with:cta2_label', 'string', 'max:500', 'regex:/^(\/(?!\/)|https?:\/\/)\S*$/'],
            'blocks_json' => ['nullable', 'array'],
            'attach_to_menu_id' => [
                'nullable',
                'integer',
                Rule::exists(Menu::class, 'id')->where('tenant_id', $tenantId),
            ],
            'parent_menu_item_id' => [
                'nullable',
                'integer',
                Rule::exists(MenuItem::class, 'id')->where('tenant_id', $tenantId),
            ],
            'menu_item_label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cta_url.required_with' => 'Add a link for the first button, or clear its text.',
            'cta2_url.required_with' => 'Add a link for the second button, or clear its text.',
            'cta_url.regex' => 'The first button link must start with / or https://.',
            'cta2_url.regex' => 'The second button link must start with / or https://.',
            'sort_order.integer' => 'Display order must be a whole number.',
        ];
    }

    private function tenantId(): ?string
    {
        $tenant = app(TenantContext::class)->getTenant();

        return $tenant ? (string) $tenant->id : null;
    }
}
