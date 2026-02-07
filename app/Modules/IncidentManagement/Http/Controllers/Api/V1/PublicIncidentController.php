<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\PublicIncidentSubmitRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentResource;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentReporter;

final class PublicIncidentController extends Controller
{
    public function submit(PublicIncidentSubmitRequest $request): IncidentResource
    {
        $data = $request->validated();

        $reporter = IncidentReporter::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'organization' => $data['organization'] ?? null,
        ]);

        $incident = Incident::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
            'priority_id' => $data['priority_id'] ?? null,
            'reported_via' => 'public',
            'reporter_id' => $reporter->id,
            'due_at' => $data['due_at'] ?? null,
            'source' => $data['source'] ?? 'public',
            'impact' => $data['impact'] ?? null,
        ]);

        return new IncidentResource($incident);
    }
}
