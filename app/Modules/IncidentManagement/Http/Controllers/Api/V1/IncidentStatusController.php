<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentStatusStoreRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentStatusUpdateRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentStatusResource;
use App\Modules\IncidentManagement\Models\IncidentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class IncidentStatusController extends Controller
{
    public function store(IncidentStatusStoreRequest $request): JsonResponse
    {
        $this->authorize('incidents.statuses.manage');

        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $status = IncidentStatus::create($data);

        return (new IncidentStatusResource($status))
            ->response()
            ->setStatusCode(201);
    }

    public function update(IncidentStatusUpdateRequest $request, IncidentStatus $status): IncidentStatusResource
    {
        $this->authorize('incidents.statuses.manage');

        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $status->fill($data);
        $status->save();

        return new IncidentStatusResource($status);
    }

    public function destroy(IncidentStatus $status): JsonResponse
    {
        $this->authorize('incidents.statuses.manage');

        $status->delete();

        return response()->json([], 204);
    }
}
