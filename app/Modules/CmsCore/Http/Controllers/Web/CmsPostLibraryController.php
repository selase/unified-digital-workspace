<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\PostStoreRequest;
use App\Modules\CmsCore\Http\Requests\PostUpdateRequest;
use App\Modules\CmsCore\Models\Category;
use App\Modules\CmsCore\Models\Media;
use App\Modules\CmsCore\Models\Post;
use App\Modules\CmsCore\Models\PostType;
use App\Modules\CmsCore\Models\Tag;
use App\Modules\CmsCore\Services\CmsSeoService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class CmsPostLibraryController extends Controller
{
    public function __construct(
        private readonly CmsSeoService $seoService
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
        $attributes = Arr::except($payload, ['category_ids', 'tag_ids', 'media_ids', 'featured_image', 'seo_title', 'seo_description', 'seo_canonical']);

        $post = Post::create($attributes);
        $this->syncRelations($post, $payload);
        $this->seoService->saveForPost($post, $request->only(['seo_title', 'seo_description', 'seo_canonical']));

        return redirect()
            ->route('cms-core.posts.show', $post)
            ->with('status', 'success')
            ->with('message', 'Post created successfully.');
    }

    public function show(Request $request, Post $post): View
    {
        abort_if(! $request->user()?->can('cms.posts.view'), 403);

        $post->load([
            'postType:id,name',
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
        $attributes = Arr::except($payload, ['category_ids', 'tag_ids', 'media_ids', 'featured_image', 'seo_title', 'seo_description', 'seo_canonical']);

        $post->fill($attributes);
        $post->save();

        $this->syncRelations($post, $payload);
        $this->seoService->saveForPost($post, $request->only(['seo_title', 'seo_description', 'seo_canonical']));

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
        $path = $file->store('cms/posts/featured', 'public');
        $originalFilename = (string) $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getClientMimeType();
        $size = $file->getSize();
        $title = pathinfo($originalFilename, PATHINFO_FILENAME);

        $media = Media::query()->create([
            'disk' => 'public',
            'path' => (string) $path,
            'original_filename' => $originalFilename,
            'filename' => basename((string) $path),
            'extension' => $extension === '' ? null : $extension,
            'mime_type' => $mimeType ?: 'application/octet-stream',
            'size_bytes' => $size ?: 0,
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
     *     seoMeta: array{seo_title: string, seo_description: string, seo_canonical: string}
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
        ];
    }
}
