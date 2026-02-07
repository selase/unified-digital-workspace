<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentPriorityStoreRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentPriorityUpdateRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentPriorityResource;
use App\Modules\IncidentManagement\Models\IncidentPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class IncidentPriorityController extends Controller
{
    public function store(IncidentPriorityStoreRequest $request): JsonResponse
    {
        $this->authorize('incidents.priorities.manage');

        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $priority = IncidentPriority::create($data);

        return (new IncidentPriorityResource($priority))
            ->response()
            ->setStatusCode(201);
    }

    public function update(IncidentPriorityUpdateRequest $request, IncidentPriority $priority): IncidentPriorityResource
    {
        $this->authorize('incidents.priorities.manage');

        $data = $request->validated();

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $priority->fill($data);
        $priority->save();

        return new IncidentPriorityResource($priority);
    }

    public function destroy(IncidentPriority $priority): JsonResponse
    {
        $this->authorize('incidents.priorities.manage');

        $priority->delete();

        return response()->json([], 204);
    }
}
