<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

final class ReadOnlyResourceController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * @return AnonymousResourceCollection<JsonResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        [$modelClass, $resourceClass, $with, $withCount] = $this->resolveRouteConfig($request);

        $perPage = $this->resolvePerPage($request);

        $query = $modelClass::query()->with($with);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        return $resourceClass::collection($query->paginate($perPage));
    }

    public function show(Request $request, int $id): JsonResource
    {
        [$modelClass, $resourceClass, $with, $withCount] = $this->resolveRouteConfig($request);

        $query = $modelClass::query()->with($with);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        return new $resourceClass($query->findOrFail($id));
    }

    /**
     * @return array{0: class-string, 1: class-string<JsonResource>, 2: array<int, string>, 3: array<int, string>}
     */
    private function resolveRouteConfig(Request $request): array
    {
        $route = $request->route();

        $defaults = is_object($route) ? $route->defaults : [];

        $modelClass = Arr::get($defaults, 'model');
        $resourceClass = Arr::get($defaults, 'resource');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            abort(500, 'Invalid or missing model configuration for API route.');
        }

        if (! is_string($resourceClass) || ! class_exists($resourceClass) || ! is_subclass_of($resourceClass, JsonResource::class)) {
            abort(500, 'Invalid or missing resource configuration for API route.');
        }

        $with = Arr::wrap(Arr::get($defaults, 'with', []));
        $withCount = Arr::wrap(Arr::get($defaults, 'withCount', []));

        return [$modelClass, $resourceClass, $with, $withCount];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
