<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\DocumentManagement\Http\Requests\DocumentStoreRequest;
use App\Modules\DocumentManagement\Http\Requests\DocumentUpdateRequest;
use App\Modules\DocumentManagement\Http\Resources\DocumentResource;
use App\Modules\DocumentManagement\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);
        $userId = (string) $user->uuid;

        $query = Document::query()
            ->visibleTo($userId)
            ->with(['currentVersion']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', $q)
                    ->orWhere('description', 'like', $q);
            });
        }

        if ($request->boolean('shared_by_me')) {
            $query->where('owner_id', $userId);
        }

        if ($request->filled('published_from')) {
            $query->whereDate('published_at', '>=', $request->date('published_from'));
        }

        if ($request->filled('published_to')) {
            $query->whereDate('published_at', '<=', $request->date('published_to'));
        }

        $documents = $query->paginate($request->integer('per_page', 15));

        return DocumentResource::collection($documents)->response();
    }

    public function store(DocumentStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['owner_id'] = (string) $request->user()?->uuid;

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $document = Document::create($data);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        $userId = (string) request()->user()?->uuid;
        $this->ensureVisible($document, $userId);

        $this->logAudit($document, 'view', $userId);

        return new DocumentResource($document->load(['currentVersion', 'versions']));
    }

    public function update(DocumentUpdateRequest $request, Document $document): DocumentResource
    {
        abort_if(! request()->user()?->can('documents.update'), 403);

        $data = $request->validated();

        if (isset($data['slug']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title'] ?? $document->title);
        }

        $document->fill($data);
        $document->save();

        return new DocumentResource($document);
    }

    public function destroy(Document $document): JsonResponse
    {
        abort_if(! request()->user()?->can('documents.delete'), 403);

        $document->delete();

        return response()->json([], 204);
    }

    public function publish(Document $document): DocumentResource
    {
        abort_if(! request()->user()?->can('documents.publish'), 403);
        $this->ensureVisible($document, (string) request()->user()?->uuid);

        $document->status = 'published';
        $document->published_at = now();
        $document->save();

        $this->logAudit($document, 'publish', (string) request()->user()?->uuid);

        return new DocumentResource($document);
    }

    private function ensureVisible(Document $document, int|string $userId): void
    {
        $visible = Document::query()
            ->visibleTo($userId)
            ->where('id', $document->id)
            ->exists();

        abort_if(! $visible, 403);
    }

    private function logAudit(Document $document, string $event, int|string $userId): void
    {
        $document->audits()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $userId,
            'event' => $event,
            'metadata' => null,
            'created_at' => now(),
        ]);
    }
}
