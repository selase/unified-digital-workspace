<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Modules\DocumentManagement\Http\Requests\DocumentVersionStoreRequest;
use App\Modules\DocumentManagement\Http\Resources\DocumentVersionResource;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentVersionController extends Controller
{
    public function index(Document $document): JsonResponse
    {
        abort_if(! request()->user()?->can('documents.view'), 403);

        return DocumentVersionResource::collection($document->versions()->latest('version_number')->get())
            ->response();
    }

    public function store(DocumentVersionStoreRequest $request, Document $document): JsonResponse
    {
        abort_if(! $request->user()?->can('documents.manage_versions'), 403);

        $file = $request->file('file');
        $nextVersion = ($document->versions()->max('version_number') ?? 0) + 1;

        $path = Helper::processUploadedFile(
            $request,
            'file',
            'document_'.$document->id.'_v'.$nextVersion,
            'documents/'.$document->id,
        );

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $nextVersion,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getClientMimeType(),
            'size_bytes' => $file?->getSize() ?? 0,
            'checksum_sha256' => $file ? hash_file('sha256', $file->getRealPath()) : null,
            'uploaded_by_id' => $request->user()?->id,
            'notes' => $request->input('notes'),
        ]);

        $document->current_version_id = $version->id;
        $document->save();

        DocumentAudit::create([
            'document_id' => $document->id,
            'user_id' => $request->user()?->id,
            'event' => 'version_uploaded',
            'metadata' => [
                'version_number' => $nextVersion,
                'filename' => $version->filename,
            ],
            'created_at' => now(),
        ]);

        return (new DocumentVersionResource($version))->response()->setStatusCode(201);
    }

    public function download(Document $document, ?int $version = null): StreamedResponse
    {
        abort_if(! request()->user()?->can('documents.view'), 403);

        $versionModel = $version
            ? $document->versions()->where('version_number', $version)->firstOrFail()
            : $document->currentVersion;

        if (! $versionModel) {
            abort(404);
        }

        DocumentAudit::create([
            'document_id' => $document->id,
            'user_id' => request()->user()?->id,
            'event' => 'download',
            'metadata' => [
                'version_number' => $versionModel->version_number,
            ],
            'created_at' => now(),
        ]);

        $filename = $versionModel->filename ?: ('document-'.$document->id.'-v'.$versionModel->version_number);

        return Storage::disk($versionModel->disk)->download($versionModel->path, $filename);
    }
}
