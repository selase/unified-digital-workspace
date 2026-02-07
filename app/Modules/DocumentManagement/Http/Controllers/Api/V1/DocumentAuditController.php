<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Http\Resources\DocumentAuditResource;
use App\Modules\DocumentManagement\Models\Document;
use Illuminate\Http\JsonResponse;

final class DocumentAuditController extends Controller
{
    public function index(Document $document): JsonResponse
    {
        abort_if(! request()->user()?->can('documents.audit.view'), 403);

        $audits = $document->audits()->latest('created_at')->paginate(request()->integer('per_page', 15));

        return DocumentAuditResource::collection($audits)->response();
    }
}
