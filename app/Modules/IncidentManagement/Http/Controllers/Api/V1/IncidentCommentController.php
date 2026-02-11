<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentCommentStoreRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentCommentResource;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentComment;
use Spatie\Activitylog\Facades\Activity;

final class IncidentCommentController extends Controller
{
    public function store(IncidentCommentStoreRequest $request, Incident $incident): IncidentCommentResource
    {
        $this->authorize('incidents.comments.manage');

        $data = $request->validated();
        $data['incident_id'] = $incident->id;
        $data['user_id'] = (string) $request->user()?->uuid;

        $comment = IncidentComment::create($data);

        Activity::getFacadeRoot()
            ->performedOn($incident)
            ->causedBy($request->user())
            ->withProperties([
                'comment_id' => $comment->id,
                'is_internal' => $comment->is_internal,
            ])
            ->event('commented')
            ->log('incident commented');

        return new IncidentCommentResource($comment);
    }
}
