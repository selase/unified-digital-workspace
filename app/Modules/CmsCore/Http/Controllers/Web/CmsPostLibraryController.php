<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\PostStoreRequest;
use App\Modules\CmsCore\Http\Requests\PostUpdateRequest;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostMeta;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Tag;
use App\Modules\CmsCore\Services\CmsSeoService;
use App\Modules\CmsCore\Services\ImageOptimizer;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class CmsPostLibraryController extends Controller
{
    /**
     * Post meta keys for the two optional buttons a template may show with a
     * post (the homepage slides use them).
     *
     * @var array<int, string>
     */
    private const BUTTON_META_KEYS = ['cta_label', 'cta_url', 'cta2_label', 'cta2_url'];

    public function __construct(
        private readonly CmsSeoService $seoService,
        private readonly ImageOptimizer $imageOptimizer,
    ) {}

    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.posts.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');
        $postTypeId = $request->integer('post_type_id');

        $posts = Post::query()
            ->with(['postType:id,name', 'author:uuid,first_name,last_name,email'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['draft', 'published', 'scheduled', 'archived'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($postTypeId > 0, function ($query) use ($postTypeId): void {
                $query->where('post_type_id', $postTypeId);
            })
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('cms-core::posts', [
            'posts' => $posts,
            'search' => $search,
            'status' => $status,
            'postTypeId' => $postTypeId,
            'postTypes' => PostType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        abort_if(! $request->user()?->can('cms.posts.create'), 403);

        return view('cms-core::posts-create', $this->formOptions());
    }

    public function store(PostStoreRequest $request): RedirectResponse
    {
        $payload = $this->normalizePayload($request->validated(), $request);
        $attributes = Arr::except($payload, ['category_ids', 'tag_ids', 'media_ids', 'featured_image', 'seo_title', 'seo_description', 'seo_canonical', 'video_url', 'audio_url', 'poster_media_id', ...self::BUTTON_META_KEYS]);

        $post = Post::create($attributes);
        $this->syncRelations($post, $payload);
        $this->seoService->saveForPost($post, $request->only(['seo_title', 'seo_description', 'seo_canonical']));
        $this->saveMeta($post, ['video_url', 'audio_url', 'poster_media_id', ...self::BUTTON_META_KEYS], $request->validated());

        return redirect()
            ->route('cms-core.posts.show', $post)
            ->with('status', 'success')
            ->with('message', 'Post created successfully.');
    }

    public function show(Request $request, Post $post): View
    {
        abort_if(! $request->user()?->can('cms.posts.view'), 403);

        $post->load([
            'postType:id,name,slug',
            'author:uuid,first_name,last_name,email',
            'editor:uuid,first_name,last_name,email',
            'categories:id,name',
            'tags:id,name',
            'featuredMedia:id,title,original_filename,filename,path,disk',
            'media:id,title,original_filename,filename,path,disk',
            'revisions:id,post_id,title,excerpt,created_at',
        ]);

        return view('cms-core::posts-show', [
            'post' => $post,
        ]);
    }

    public function edit(Request $request, Post $post): View|Factory
    {
        abort_if(! $request->user()?->can('cms.posts.update'), 403);

        $post->load(['categories:id', 'tags:id', 'media:id']);

        return view('cms-core::posts-edit', $this->formOptions($post));
    }

    public function update(PostUpdateRequest $request, Post $post): RedirectResponse
    {
        $payload = $this->normalizePayload($request->validated(), $request);
        $attributes = Arr::except($payload, ['category_ids', 'tag_ids', 'media_ids', 'featured_image', 'seo_title', 'seo_description', 'seo_canonical', 'video_url', 'audio_url', 'poster_media_id', ...self::BUTTON_META_KEYS]);

        $post->fill($attributes);
        $post->save();

        $this->syncRelations($post, $payload);
        $this->seoService->saveForPost($post, $request->only(['seo_title', 'seo_description', 'seo_canonical']));
        $this->saveMeta($post, ['video_url', 'audio_url', 'poster_media_id', ...self::BUTTON_META_KEYS], $request->validated());

        return redirect()
            ->route('cms-core.posts.show', $post)
            ->with('status', 'success')
            ->with('message', 'Post updated successfully.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_if(! $request->user()?->can('cms.posts.delete'), 403);

        $post->delete();

        return redirect()
            ->route('cms-core.posts.index')
            ->with('status', 'success')
            ->with('message', 'Post removed from library.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, Request $request): array
    {
        $payload['author_id'] = $payload['author_id'] ?? (string) $request->user()?->uuid;
        $payload['editor_id'] = (string) $request->user()?->uuid;

        if (empty($payload['slug'] ?? null) && isset($payload['title'])) {
            $payload['slug'] = Str::slug((string) $payload['title']);
        }

        if (($payload['status'] ?? null) === 'published' && empty($payload['published_at'])) {
            $payload['published_at'] = now();
        }

        if (($payload['status'] ?? null) !== 'scheduled') {
            $payload['scheduled_for'] = null;
        }

        if ($request->hasFile('featured_image')) {
            $uploadedBy = (string) ($request->user()?->uuid ?? '');
            $payload['featured_media_id'] = $this->storeFeaturedImage(
                $request->file('featured_image'),
                $uploadedBy
            );
        }

        return $payload;
    }

    private function storeFeaturedImage(UploadedFile $file, string $uploadedBy): int
    {
        $stored = $this->imageOptimizer->store($file, 'cms/posts/featured');
        $originalFilename = (string) $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getClientMimeType();
        $title = pathinfo($originalFilename, PATHINFO_FILENAME);

        $media = Media::query()->create([
            'disk' => 'tenant',
            'path' => $stored['path'],
            'original_filename' => $originalFilename,
            'filename' => basename($stored['path']),
            'extension' => $extension === '' ? null : $extension,
            'mime_type' => $mimeType ?: 'application/octet-stream',
            'size_bytes' => $stored['size_bytes'],
            'checksum_sha256' => $stored['checksum_sha256'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'title' => $title !== '' ? $title : null,
            'uploaded_by' => $uploadedBy,
            'source' => 'upload',
            'is_public' => true,
        ]);

        return (int) $media->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncRelations(Post $post, array $payload): void
    {
        if (array_key_exists('category_ids', $payload)) {
            $categories = [];

            foreach ((array) Arr::get($payload, 'category_ids', []) as $id) {
                $categories[(int) $id] = ['sort_order' => 0];
            }

            $post->categories()->sync($categories);
        }

        if (array_key_exists('tag_ids', $payload)) {
            $post->tags()->sync((array) Arr::get($payload, 'tag_ids', []));
        }

        if (array_key_exists('media_ids', $payload)) {
            $media = [];

            foreach ((array) Arr::get($payload, 'media_ids', []) as $id) {
                $media[(int) $id] = ['role' => null, 'sort_order' => 0];
            }

            $post->media()->sync($media);
        }
    }

    /**
     * @return array{
     *     post: Post|null,
     *     postTypes: \Illuminate\Database\Eloquent\Collection<int, PostType>,
     *     categories: \Illuminate\Database\Eloquent\Collection<int, Category>,
     *     tags: \Illuminate\Database\Eloquent\Collection<int, Tag>,
     *     mediaItems: \Illuminate\Database\Eloquent\Collection<int, Media>,
     *     parentPosts: \Illuminate\Database\Eloquent\Collection<int, Post>,
     *     statusOptions: array<int, string>,
     *     seoMeta: array{seo_title: string, seo_description: string, seo_canonical: string},
     *     mediaMeta: array{video_url: string, audio_url: string, poster_media_id: string},
     *     buttonMeta: array{cta_label: string, cta_url: string, cta2_label: string, cta2_url: string}
     * }
     */
    private function formOptions(?Post $post = null): array
    {
        return [
            'post' => $post,
            'postTypes' => PostType::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'mediaItems' => Media::query()->orderByDesc('id')->limit(50)->get(['id', 'title', 'filename', 'original_filename']),
            'parentPosts' => Post::query()
                ->when($post !== null, function ($query) use ($post): void {
                    $query->where('id', '!=', $post->getKey());
                })
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'title']),
            'statusOptions' => ['draft', 'published', 'scheduled', 'archived'],
            'seoMeta' => $post ? $this->seoService->getMetaValues($post) : ['seo_title' => '', 'seo_description' => '', 'seo_canonical' => ''],
            'mediaMeta' => $post ? $this->getMediaMeta($post) : ['video_url' => '', 'audio_url' => '', 'poster_media_id' => ''],
            'buttonMeta' => $post ? $this->getButtonMeta($post) : ['cta_label' => '', 'cta_url' => '', 'cta2_label' => '', 'cta2_url' => ''],
        ];
    }

    /**
     * @return array{video_url: string, audio_url: string, poster_media_id: string}
     */
    private function getMediaMeta(Post $post): array
    {
        $meta = PostMeta::query()
            ->where('post_id', $post->id)
            ->whereIn('key', ['video_url', 'audio_url', 'poster_media_id'])
            ->pluck('value', 'key');

        return [
            'video_url' => (string) ($meta['video_url'] ?? ''),
            'audio_url' => (string) ($meta['audio_url'] ?? ''),
            'poster_media_id' => (string) ($meta['poster_media_id'] ?? ''),
        ];
    }

    /**
     * @return array{cta_label: string, cta_url: string, cta2_label: string, cta2_url: string}
     */
    private function getButtonMeta(Post $post): array
    {
        $meta = PostMeta::query()
            ->where('post_id', $post->id)
            ->whereIn('key', self::BUTTON_META_KEYS)
            ->pluck('value', 'key');

        return [
            'cta_label' => (string) ($meta['cta_label'] ?? ''),
            'cta_url' => (string) ($meta['cta_url'] ?? ''),
            'cta2_label' => (string) ($meta['cta2_label'] ?? ''),
            'cta2_url' => (string) ($meta['cta2_url'] ?? ''),
        ];
    }

    /**
     * Store each submitted key as post meta; a key left empty is removed.
     * Keys absent from the submission are left untouched.
     *
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $values
     */
    private function saveMeta(Post $post, array $keys, array $values): void
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if ($value !== null && $value !== '') {
                PostMeta::query()->updateOrCreate(
                    ['post_id' => $post->id, 'key' => $key],
                    ['value' => $value]
                );
            } else {
                PostMeta::query()
                    ->where('post_id', $post->id)
                    ->where('key', $key)
                    ->delete();
            }
        }
    }
}
