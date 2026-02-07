<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\CategoryStoreRequest;
use App\Modules\CmsCore\Http\Requests\CategoryUpdateRequest;
use App\Modules\CmsCore\Http\Resources\CategoryResource;
use App\Modules\CmsCore\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class CategoryController extends Controller
{
    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = Category::create($data);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->fill($data);
        $category->save();

        return new CategoryResource($category);
    }
}
