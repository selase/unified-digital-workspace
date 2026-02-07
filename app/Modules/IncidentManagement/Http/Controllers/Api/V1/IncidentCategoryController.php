<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentCategoryStoreRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentCategoryUpdateRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentCategoryResource;
use App\Modules\IncidentManagement\Models\IncidentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class IncidentCategoryController extends Controller
{
    public function store(IncidentCategoryStoreRequest $request): JsonResponse
    {
        $this->authorize('incidents.categories.manage');

        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = IncidentCategory::create($data);

        return (new IncidentCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(IncidentCategoryUpdateRequest $request, IncidentCategory $category): IncidentCategoryResource
    {
        $this->authorize('incidents.categories.manage');

        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->fill($data);
        $category->save();

        return new IncidentCategoryResource($category);
    }

    public function destroy(IncidentCategory $category): JsonResponse
    {
        $this->authorize('incidents.categories.manage');

        $category->delete();

        return response()->json([], 204);
    }
}
