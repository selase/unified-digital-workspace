<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Http\Requests\SettingStoreRequest;
use App\Modules\CmsCore\Http\Requests\SettingUpdateRequest;
use App\Modules\CmsCore\Http\Resources\SettingResource;
use App\Modules\CmsCore\Models\Setting;
use Illuminate\Http\JsonResponse;

final class SettingController extends Controller
{
    public function store(SettingStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['key_unique']);

        $setting = Setting::create($data);

        return (new SettingResource($setting))
            ->response()
            ->setStatusCode(201);
    }

    public function update(SettingUpdateRequest $request, Setting $setting): SettingResource
    {
        $data = $request->validated();
        unset($data['key_unique']);

        $setting->fill($data);
        $setting->save();

        return new SettingResource($setting);
    }
}
