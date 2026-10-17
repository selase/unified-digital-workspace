<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Modules\DocumentManagement\Http\Requests\DocumentStoreRequest;
use App\Modules\DocumentManagement\Http\Requests\DocumentVersionStoreRequest;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentWebController extends Controller
{
    public const CATEGORIES = [
        'Standard Operating Procedure (SOP)',
        'Clinical Practice Guideline',
        'Institutional Policy',
        'Administrative Manual',
        'Quality Form / Template',
        'Statutory & Compliance',
        'Technical Specification',
    ];

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.create'), 403);

        return view('document-management::create', [
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(DocumentStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.create'), 403);

        $validated = $request->validated();

        $baseSlug = empty($validated['slug']) ? Str::slug($validated['title']) : Str::slug($validated['slug']);
        $slug = $baseSlug;
        $counter = 1;
        while (Document::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }
        $validated['slug'] = $slug;

        $document = Document::create([
            'tenant_id' => session('active_tenant_id'),
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'visibility' => $validated['visibility'] ?? ['tenant_wide' => true],
            'owner_id' => (string) $user->uuid,
            'category' => $validated['category'] ?? null,
            'tags' => $validated['tags'] ?? [],
            'metadata' => $validated['metadata'] ?? [],
            'published_at' => ($validated['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = Helper::processUploadedFile(
                $request,
                'file',
                'doc_'.$document->id.'_v1',
                'documents/'.$document->id,
                'public'
            );

            $version = DocumentVersion::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'version_number' => 1,
                'disk' => 'public',
                'path' => $path,
                'filename' => $file?->getClientOriginalName(),
                'mime_type' => $file?->getClientMimeType(),
                'size_bytes' => $file?->getSize() ?? 0,
                'checksum_sha256' => $file ? hash_file('sha256', $file->getRealPath()) : null,
                'uploaded_by_id' => (string) $user->uuid,
                'notes' => $validated['notes'] ?? 'Initial version upload',
            ]);

            $document->current_version_id = $version->id;
            $document->save();

            DocumentAudit::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'user_id' => (string) $user->uuid,
                'event' => 'version_uploaded',
                'metadata' => [
                    'version_number' => 1,
                    'filename' => $version->filename,
                ],
                'created_at' => now(),
            ]);
        }

        DocumentAudit::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'user_id' => (string) $user->uuid,
            'event' => 'create',
            'metadata' => [
                'title' => $document->title,
                'category' => $document->category,
            ],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Controlled document registered successfully.');
    }

    public function show(Request $request, Document $document): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $document->load([
            'owner:uuid,first_name,last_name,email',
            'currentVersion.uploadedBy:uuid,first_name,last_name,email',
            'versions.uploadedBy:uuid,first_name,last_name,email',
            'audits.user:uuid,first_name,last_name,email',
            'quizzes',
        ]);

        $this->logAudit($document, 'view', (string) $user->uuid);

        return view('document-management::show', [
            'document' => $document,
            'canManageVersions' => (bool) $user->can('documents.manage_versions'),
            'canPublish' => (bool) $user->can('documents.publish'),
            'canUpdate' => (bool) $user->can('documents.update'),
        ]);
    }

    public function storeVersion(DocumentVersionStoreRequest $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.manage_versions'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $file = $request->file('file');
        $nextVersion = ($document->versions()->max('version_number') ?? 0) + 1;

        $path = Helper::processUploadedFile(
            $request,
            'file',
            'doc_'.$document->id.'_v'.$nextVersion,
            'documents/'.$document->id,
            'public'
        );

        $version = DocumentVersion::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'version_number' => $nextVersion,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file?->getClientOriginalName(),
            'mime_type' => $file?->getClientMimeType(),
            'size_bytes' => $file?->getSize() ?? 0,
            'checksum_sha256' => $file ? hash_file('sha256', $file->getRealPath()) : null,
            'uploaded_by_id' => (string) $user->uuid,
            'notes' => $request->input('notes'),
        ]);

        $document->current_version_id = $version->id;
        $document->save();

        DocumentAudit::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'user_id' => (string) $user->uuid,
            'event' => 'version_uploaded',
            'metadata' => [
                'version_number' => $nextVersion,
                'filename' => $version->filename,
            ],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'New version (v'.$nextVersion.') uploaded and set as active.');
    }

    public function publish(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.publish'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $document->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->logAudit($document, 'publish', (string) $user->uuid);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document published to institutional catalog.');
    }

    public function archive(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.update'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $document->update([
            'status' => 'archived',
        ]);

        $this->logAudit($document, 'archive', (string) $user->uuid);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document archived.');
    }

    public function download(Request $request, Document $document, ?int $version = null): StreamedResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $versionModel = $version !== null
            ? $document->versions()->where('version_number', $version)->firstOrFail()
            : $document->currentVersion;

        if (! $versionModel) {
            abort(404, 'Version not found.');
        }

        $disk = $versionModel->disk ?: 'public';
        if (! Storage::disk($disk)->exists($versionModel->path)) {
            abort(404, 'File not found on storage.');
        }

        $this->logAudit($document, 'download', (string) $user->uuid, [
            'version_number' => $versionModel->version_number,
            'filename' => $versionModel->filename,
        ]);

        return Storage::disk($disk)->download($versionModel->path, $versionModel->filename);
    }

    private function ensureVisible(Document $document, string $userId): void
    {
        $visible = Document::query()
            ->visibleTo($userId)
            ->where('id', $document->id)
            ->exists();

        abort_if(! $visible, 403);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function logAudit(Document $document, string $event, string $userId, ?array $metadata = null): void
    {
        $document->audits()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $userId,
            'event' => $event,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
