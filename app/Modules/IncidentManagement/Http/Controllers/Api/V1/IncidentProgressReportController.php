<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentProgressReportStoreRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentProgressReportResource;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentProgressReport;
use Illuminate\Http\JsonResponse;

final class IncidentProgressReportController extends Controller
{
    public function store(IncidentProgressReportStoreRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('incidents.update');

        $report = IncidentProgressReport::create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()?->id,
            'body' => $request->string('body'),
            'is_internal' => $request->boolean('is_internal', false),
        ]);

        return (new IncidentProgressReportResource($report->load(['comments', 'attachments'])))
            ->response()
            ->setStatusCode(201);
    }
}
