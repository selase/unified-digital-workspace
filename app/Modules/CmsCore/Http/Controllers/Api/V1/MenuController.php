<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\MenuStoreRequest;
use App\Modules\CmsCore\Http\Requests\MenuUpdateRequest;
use App\Modules\CmsCore\Http\Resources\MenuResource;
use App\Modules\CmsCore\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class MenuController extends Controller
{
    public function store(MenuStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $menu = Menu::create($data);

        return (new MenuResource($menu))
            ->response()
            ->setStatusCode(201);
    }

    public function update(MenuUpdateRequest $request, Menu $menu): MenuResource
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $menu->fill($data);
        $menu->save();

        return new MenuResource($menu);
    }
}
