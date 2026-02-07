<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Modules\IncidentManagement\Http\Requests\IncidentAttachmentStoreRequest;
use App\Modules\IncidentManagement\Http\Resources\IncidentAttachmentResource;
use App\Modules\IncidentManagement\Models\Incident;
use App\Modules\IncidentManagement\Models\IncidentAttachment;
use App\Modules\IncidentManagement\Models\IncidentComment;
use App\Modules\IncidentManagement\Models\IncidentProgressReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class IncidentAttachmentController extends Controller
{
    public function store(IncidentAttachmentStoreRequest $request, Incident $incident): JsonResponse
    {
        $this->authorize('incidents.update');

        $data = $request->validated();

        if (isset($data['comment_id']) && ! $this->commentBelongsToIncident($data['comment_id'], $incident->id)) {
            throw new UnprocessableEntityHttpException('Comment does not belong to this incident.');
        }

        if (isset($data['progress_report_id']) && ! $this->progressReportBelongsToIncident($data['progress_report_id'], $incident->id)) {
            throw new UnprocessableEntityHttpException('Progress report does not belong to this incident.');
        }

        $path = Helper::processUploadedFile(
            $request,
            'file',
            'incident_'.$incident->id,
            'incidents/'.$incident->id,
        );

        $file = $request->file('file');

        $attachment = IncidentAttachment::create([
            'incident_id' => $incident->id,
            'comment_id' => $data['comment_id'] ?? null,
            'progress_report_id' => $data['progress_report_id'] ?? null,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getClientMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file?->getSize() ?? 0,
            'uploaded_by_id' => $request->user()?->id,
        ]);

        return (new IncidentAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    private function commentBelongsToIncident(int $commentId, string $incidentId): bool
    {
        return IncidentComment::query()
            ->where('id', $commentId)
            ->where('incident_id', $incidentId)
            ->exists();
    }

    private function progressReportBelongsToIncident(int $reportId, string $incidentId): bool
    {
        return IncidentProgressReport::query()
            ->where('id', $reportId)
            ->where('incident_id', $incidentId)
            ->exists();
    }
}
