<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\MediaStoreRequest;
use App\Modules\CmsCore\Http\Requests\MediaUpdateRequest;
use App\Modules\CmsCore\Http\Resources\MediaResource;
use App\Modules\CmsCore\Models\Media;
use Illuminate\Http\JsonResponse;

final class MediaController extends Controller
{
    public function store(MediaStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $attributes = array_diff_key($data, ['post_ids' => true]);

        $media = Media::create($attributes);

        if (array_key_exists('post_ids', $data)) {
            $media->posts()->sync($data['post_ids']);
        }

        return (new MediaResource($media->load('variants')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(MediaUpdateRequest $request, Media $media): MediaResource
    {
        $data = $request->validated();
        $attributes = array_diff_key($data, ['post_ids' => true]);

        $media->fill($attributes);
        $media->save();

        if (array_key_exists('post_ids', $data)) {
            $media->posts()->sync($data['post_ids']);
        }

        return new MediaResource($media->load('variants'));
    }

    public function destroy(Media $media): JsonResponse
    {
        $media->delete();

        return response()->json([], 204);
    }
}
