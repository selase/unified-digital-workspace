<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentTaskStoreRequest;
use App\Modules\IncidentManagement\Http\Requests\IncidentTaskUpdateRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentTaskResource;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentTask;

final class IncidentTaskController extends Controller
{
    public function store(IncidentTaskStoreRequest $request, Incident $incident): IncidentTaskResource
    {
        $this->authorize('incidents.tasks.manage');

        $data = $request->validated();
        $data['incident_id'] = $incident->id;

        $task = IncidentTask::create($data);

        return new IncidentTaskResource($task);
    }

    public function update(IncidentTaskUpdateRequest $request, Incident $incident, IncidentTask $task): IncidentTaskResource
    {
        $this->authorize('incidents.tasks.manage');

        if ($task->incident_id !== $incident->id) {
            abort(404);
        }

        $task->fill($request->validated());
        $task->save();

        return new IncidentTaskResource($task);
    }
}
