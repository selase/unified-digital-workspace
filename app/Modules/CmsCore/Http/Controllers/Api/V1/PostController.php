<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\PostStoreRequest;
use App\Modules\CmsCore\Http\Requests\PostUpdateRequest;
use App\Modules\CmsCore\Http\Resources\PostResource;
use App\Modules\CmsCore\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class PostController extends Controller
{
    public function store(PostStoreRequest $request): JsonResponse
    {
        $data = $this->prepareData($request->validated());
        $attributes = Arr::except($data, ['category_ids', 'tag_ids', 'media_ids']);

        $post = Post::create($attributes);
        $this->syncRelations($post, $data);

        return (new PostResource($post->load(['postType', 'categories', 'tags', 'featuredMedia', 'media', 'meta', 'revisions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PostUpdateRequest $request, Post $post): PostResource
    {
        $data = $this->prepareData($request->validated());
        $attributes = Arr::except($data, ['category_ids', 'tag_ids', 'media_ids']);

        $post->fill($attributes);
        $post->save();

        $this->syncRelations($post, $data);

        return new PostResource($post->load(['postType', 'categories', 'tags', 'featuredMedia', 'media', 'meta', 'revisions']));
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json([], 204);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        if (isset($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(Post $post, array $data): void
    {
        if (array_key_exists('category_ids', $data)) {
            $categories = [];

            foreach (Arr::get($data, 'category_ids', []) as $id) {
                $categories[(int) $id] = ['sort_order' => 0];
            }

            $post->categories()->sync($categories);
        }

        if (array_key_exists('tag_ids', $data)) {
            $post->tags()->sync(Arr::get($data, 'tag_ids', []));
        }

        if (array_key_exists('media_ids', $data)) {
            $media = [];

            foreach (Arr::get($data, 'media_ids', []) as $id) {
                $media[(int) $id] = ['role' => null, 'sort_order' => 0];
            }

            $post->media()->sync($media);
        }
    }
}
