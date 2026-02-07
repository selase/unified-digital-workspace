<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\TagStoreRequest;
use App\Modules\CmsCore\Http\Requests\TagUpdateRequest;
use App\Modules\CmsCore\Http\Resources\TagResource;
use App\Modules\CmsCore\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class TagController extends Controller
{
    public function store(TagStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tag = Tag::create($data);

        return (new TagResource($tag))
            ->response()
            ->setStatusCode(201);
    }

    public function update(TagUpdateRequest $request, Tag $tag): TagResource
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tag->fill($data);
        $tag->save();

        return new TagResource($tag);
    }
}
