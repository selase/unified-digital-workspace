<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\IncidentManagement\Http\Requests\IncidentProgressCommentStoreRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentProgressCommentResource;
use App\Modules\IncidentManagement\Models\IncidentProgressComment;
use App\Modules\IncidentManagement\Models\IncidentProgressReport;
use Illuminate\Http\JsonResponse;

final class IncidentProgressCommentController extends Controller
{
    public function store(IncidentProgressCommentStoreRequest $request, IncidentProgressReport $progressReport): JsonResponse
    {
        $this->authorize('incidents.update');

        $comment = IncidentProgressComment::create([
            'progress_report_id' => $progressReport->id,
            'user_id' => (string) $request->user()?->uuid,
            'body' => $request->string('body'),
        ]);

        return (new IncidentProgressCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }
}
