<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\PostTypeStoreRequest;
use App\Modules\CmsCore\Http\Requests\PostTypeUpdateRequest;
use App\Modules\CmsCore\Http\Resources\PostTypeResource;
use App\Modules\CmsCore\Models\PostType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class PostTypeController extends Controller
{
    public function store(PostTypeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $postType = PostType::create($data);

        return (new PostTypeResource($postType))
            ->response()
            ->setStatusCode(201);
    }

    public function update(PostTypeUpdateRequest $request, PostType $postType): PostTypeResource
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $postType->fill($data);
        $postType->save();

        return new PostTypeResource($postType);
    }
}
