<?php

declare(strict_types=1);

namespace App\Modules\DocumentManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Libraries\Helper;
use App\Models\User;
use App\Modules\DocumentManagement\Http\Requests\DocumentStoreRequest;
use App\Modules\DocumentManagement\Http\Requests\DocumentUpdateRequest;
use App\Modules\DocumentManagement\Http\Requests\DocumentVersionStoreRequest;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\DocumentManagement\Models\DocumentAcknowledgement;
use App\Modules\DocumentManagement\Models\DocumentAudit;
use App\Modules\DocumentManagement\Models\DocumentQuiz;
use App\Modules\DocumentManagement\Models\DocumentQuizAttempt;
use App\Modules\DocumentManagement\Models\DocumentQuizQuestion;
use App\Modules\DocumentManagement\Models\DocumentVersion;
use App\Modules\WorkflowAutomation\Events\WorkflowDomainEvent;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $tenantId = $this->resolveTenantId($user);

        $users = User::query()
            ->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId));
            })
            ->where(function ($query): void {
                $query->where('status', User::STATUS_ACTIVE)
                    ->orWhereNull('status');
            })
            ->orderBy('first_name')
            ->get();

        $activeDocuments = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Document::STATUS_PUBLISHED)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'category']);

        return view('document-management::create', [
            'categories' => self::CATEGORIES,
            'users' => $users,
            'activeDocuments' => $activeDocuments,
        ]);
    }

    public function store(DocumentStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.create'), 403);

        $validated = $request->validated();
        $tenantId = $this->resolveTenantId($user);

        $baseSlug = empty($validated['slug']) ? Str::slug($validated['title']) : Str::slug($validated['slug']);
        $slug = $baseSlug;
        $counter = 1;
        while (Document::query()->where('slug', $slug)->exists() && $counter <= 100) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }
        $validated['slug'] = $slug;

        $initialStatus = $validated['status'] ?? Document::STATUS_DRAFT;

        $document = DB::transaction(function () use ($validated, $tenantId, $user, $request, $initialStatus): Document {
            $document = Document::create([
                'tenant_id' => $tenantId,
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'status' => $initialStatus,
                'visibility' => $validated['visibility'] ?? ['tenant_wide' => true],
                'owner_id' => (string) $user->uuid,
                'reviewer_id' => $validated['reviewer_id'] ?? null,
                'approver_id' => $validated['approver_id'] ?? null,
                'review_due_at' => $validated['review_due_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'supersedes_id' => $validated['supersedes_id'] ?? null,
                'category' => $validated['category'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'metadata' => $validated['metadata'] ?? [],
                'published_at' => $initialStatus === Document::STATUS_PUBLISHED ? now() : null,
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
                    'status' => $document->status,
                ],
                'created_at' => now(),
            ]);

            if ($document->status === Document::STATUS_PUBLISHED) {
                if ($document->supersedes_id) {
                    $superseded = Document::query()->find($document->supersedes_id);
                    if ($superseded) {
                        $superseded->update([
                            'status' => Document::STATUS_OBSOLETE,
                            'superseded_by_id' => $document->id,
                        ]);

                        DocumentAudit::create([
                            'tenant_id' => $superseded->tenant_id,
                            'document_id' => $superseded->id,
                            'user_id' => (string) $user->uuid,
                            'event' => 'obsoleted',
                            'metadata' => ['superseded_by_id' => $document->id],
                            'created_at' => now(),
                        ]);
                    }
                }

                WorkflowDomainEvent::dispatch(
                    (string) $document->tenant_id,
                    'document.published',
                    [
                        'id' => $document->id,
                        'title' => $document->title,
                        'slug' => $document->slug,
                        'status' => $document->status,
                        'published_at' => now()->toIso8601String(),
                    ]
                );
            }

            return $document;
        });

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
            'reviewer:uuid,first_name,last_name,email',
            'approver:uuid,first_name,last_name,email',
            'supersedes:id,title,slug,status',
            'supersededBy:id,title,slug,status',
            'currentVersion.uploadedBy:uuid,first_name,last_name,email',
            'versions.uploadedBy:uuid,first_name,last_name,email',
            'audits.user:uuid,first_name,last_name,email',
            'quizzes.questions',
            'acknowledgements.user:uuid,first_name,last_name,email',
        ]);

        $this->logAudit($document, 'view', (string) $user->uuid);

        $isAcknowledged = $document->isAcknowledgedBy((string) $user->uuid);
        $ackCount = $document->acknowledgements->count();

        $tenantUsersQuery = User::query()
            ->where(function ($query) use ($document): void {
                $query->where('tenant_id', $document->tenant_id)
                    ->orWhereHas('tenants', fn ($q) => $q->where('tenants.id', $document->tenant_id));
            })
            ->where(function ($query): void {
                $query->where('status', User::STATUS_ACTIVE)
                    ->orWhereNull('status');
            });

        $tenantUsersCount = $tenantUsersQuery->count();
        $ackPercentage = $tenantUsersCount > 0 ? (int) round(($ackCount / $tenantUsersCount) * 100) : 0;
        $tenantUsers = $tenantUsersQuery->orderBy('first_name')->get();

        return view('document-management::show', [
            'document' => $document,
            'canManageVersions' => (bool) $user->can('documents.manage_versions'),
            'canPublish' => (bool) $user->can('documents.publish'),
            'canUpdate' => (bool) $user->can('documents.update'),
            'canDelete' => (bool) $user->can('documents.delete'),
            'canReview' => (bool) ($user->can('documents.review') || (string) $document->reviewer_id === (string) $user->uuid),
            'canApprove' => (bool) ($user->can('documents.approve') || (string) $document->approver_id === (string) $user->uuid),
            'canManageQuizzes' => (bool) $user->can('documents.manage_quizzes'),
            'isAcknowledged' => $isAcknowledged,
            'ackCount' => $ackCount,
            'tenantUsersCount' => $tenantUsersCount,
            'ackPercentage' => $ackPercentage,
            'tenantUsers' => $tenantUsers,
        ]);
    }

    public function edit(Request $request, Document $document): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.update'), 403);

        $tenantId = $this->resolveTenantId($user);

        $users = User::query()
            ->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId));
            })
            ->where(function ($query): void {
                $query->where('status', User::STATUS_ACTIVE)
                    ->orWhereNull('status');
            })
            ->orderBy('first_name')
            ->get();

        $activeDocuments = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $document->id)
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'category']);

        return view('document-management::edit', [
            'document' => $document,
            'categories' => self::CATEGORIES,
            'users' => $users,
            'activeDocuments' => $activeDocuments,
        ]);
    }

    public function update(DocumentUpdateRequest $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.update'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $validated = $request->validated();
        $document->update($validated);

        $this->logAudit($document, 'update', (string) $user->uuid, [
            'title' => $document->title,
            'status' => $document->status,
        ]);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Controlled document updated successfully.');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.delete'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $title = $document->title;
        $this->logAudit($document, 'delete', (string) $user->uuid);
        $document->delete();

        return redirect()
            ->route('document-management.documents.index')
            ->with('status', 'success')
            ->with('message', "Document '{$title}' soft-deleted.");
    }

    public function submitForReview(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! ($user?->can('documents.update') || (string) $document->owner_id === (string) $user->uuid), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        if (! in_array($document->status, [Document::STATUS_DRAFT, Document::STATUS_UNDER_REVISION], true)) {
            return back()->withErrors(['status' => 'Document is not in a submittable state.']);
        }

        $validated = $request->validate([
            'reviewer_id' => ['nullable', 'string', 'uuid'],
            'review_due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document->update([
            'status' => Document::STATUS_IN_REVIEW,
            'reviewer_id' => $validated['reviewer_id'] ?? $document->reviewer_id,
            'review_due_at' => $validated['review_due_at'] ?? $document->review_due_at,
        ]);

        $this->logAudit($document, 'submitted_for_review', (string) $user->uuid, [
            'reviewer_id' => $document->reviewer_id,
            'review_due_at' => $document->review_due_at?->toIso8601String(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document submitted for ISO review.');
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! ($user?->can('documents.approve') || (string) $document->approver_id === (string) $user->uuid), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        if ($document->status !== Document::STATUS_IN_REVIEW) {
            return back()->withErrors(['status' => 'Document is not currently pending review.']);
        }

        $validated = $request->validate([
            'review_comments' => ['nullable', 'string', 'max:3000'],
        ]);

        $document->update([
            'status' => Document::STATUS_APPROVED,
            'approver_id' => (string) $user->uuid,
            'review_comments' => $validated['review_comments'] ?? $document->review_comments,
        ]);

        $this->logAudit($document, 'approved', (string) $user->uuid, [
            'approver_id' => (string) $user->uuid,
            'comments' => $validated['review_comments'] ?? null,
        ]);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document review approved. Ready for catalog publication.');
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! ($user?->can('documents.review') || $user?->can('documents.approve') || (string) $document->reviewer_id === (string) $user->uuid), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        if ($document->status !== Document::STATUS_IN_REVIEW) {
            return back()->withErrors(['status' => 'Document is not currently pending review.']);
        }

        $validated = $request->validate([
            'review_comments' => ['required', 'string', 'max:3000'],
        ]);

        $document->update([
            'status' => Document::STATUS_UNDER_REVISION,
            'review_comments' => $validated['review_comments'],
        ]);

        $this->logAudit($document, 'rejected', (string) $user->uuid, [
            'comments' => $validated['review_comments'],
        ]);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'warning')
            ->with('message', 'Document returned for revision with feedback.');
    }

    public function publish(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.publish'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        DB::transaction(function () use ($document, $user): void {
            if ($document->supersedes_id) {
                $superseded = Document::query()->find($document->supersedes_id);
                if ($superseded) {
                    $superseded->update([
                        'status' => Document::STATUS_OBSOLETE,
                        'superseded_by_id' => $document->id,
                    ]);

                    DocumentAudit::create([
                        'tenant_id' => $superseded->tenant_id,
                        'document_id' => $superseded->id,
                        'user_id' => (string) $user->uuid,
                        'event' => 'obsoleted',
                        'metadata' => ['superseded_by_id' => $document->id],
                        'created_at' => now(),
                    ]);
                }
            }

            $document->update([
                'status' => Document::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            $this->logAudit($document, 'publish', (string) $user->uuid);

            WorkflowDomainEvent::dispatch(
                (string) $document->tenant_id,
                'document.published',
                [
                    'id' => $document->id,
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'status' => $document->status,
                    'published_at' => now()->toIso8601String(),
                ]
            );
        });

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document published to institutional catalog.');
    }

    public function revise(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.update'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        if ($document->status !== Document::STATUS_PUBLISHED) {
            return back()->withErrors(['status' => 'Only published documents can be moved to revision.']);
        }

        $document->update([
            'status' => Document::STATUS_UNDER_REVISION,
        ]);

        $this->logAudit($document, 'revised', (string) $user->uuid);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document moved to under revision state.');
    }

    public function markObsolete(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.update'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $document->update([
            'status' => Document::STATUS_OBSOLETE,
        ]);

        $this->logAudit($document, 'obsoleted', (string) $user->uuid);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document marked obsolete/superseded.');
    }

    public function archive(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.update'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $document->update([
            'status' => Document::STATUS_ARCHIVED,
        ]);

        $this->logAudit($document, 'archive', (string) $user->uuid);

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Document archived.');
    }

    public function acknowledge(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        if ($document->status !== Document::STATUS_PUBLISHED) {
            return back()->withErrors(['acknowledgement' => 'Only active published documents require read acknowledgement.']);
        }

        DocumentAcknowledgement::query()->firstOrCreate(
            [
                'document_id' => $document->id,
                'user_id' => (string) $user->uuid,
            ],
            [
                'tenant_id' => $document->tenant_id,
                'acknowledged_at' => now(),
                'notes' => $request->input('notes'),
            ]
        );

        $this->logAudit($document, 'acknowledged', (string) $user->uuid);

        return back()
            ->with('status', 'success')
            ->with('message', 'Read acknowledgement registered successfully.');
    }

    public function documentCompliance(Request $request, Document $document): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $tenantUsers = User::query()
            ->where(function ($query) use ($document): void {
                $query->where('tenant_id', $document->tenant_id)
                    ->orWhereHas('tenants', fn ($q) => $q->where('tenants.id', $document->tenant_id));
            })
            ->where(function ($query): void {
                $query->where('status', User::STATUS_ACTIVE)
                    ->orWhereNull('status');
            })
            ->orderBy('first_name')
            ->get();

        $acknowledgements = DocumentAcknowledgement::query()
            ->where('document_id', $document->id)
            ->get()
            ->keyBy('user_id');

        $totalUsers = $tenantUsers->count();
        $acknowledgedCount = $acknowledgements->count();
        $complianceRate = $totalUsers > 0 ? (int) round(($acknowledgedCount / $totalUsers) * 100) : 0;

        return view('document-management::compliance', [
            'document' => $document,
            'tenantUsers' => $tenantUsers,
            'acknowledgements' => $acknowledgements,
            'totalUsers' => $totalUsers,
            'acknowledgedCount' => $acknowledgedCount,
            'complianceRate' => $complianceRate,
        ]);
    }

    public function complianceOverview(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $tenantId = $this->resolveTenantId($user);

        $publishedDocs = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Document::STATUS_PUBLISHED)
            ->withCount('acknowledgements')
            ->orderBy('title')
            ->get();

        $totalUsers = User::query()
            ->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId));
            })
            ->where(function ($query): void {
                $query->where('status', User::STATUS_ACTIVE)
                    ->orWhereNull('status');
            })
            ->count();

        return view('document-management::compliance-overview', [
            'documents' => $publishedDocs,
            'totalUsers' => $totalUsers,
        ]);
    }

    public function createQuiz(Request $request, Document $document): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.manage_quizzes'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        return view('document-management::quizzes.create', compact('document'));
    }

    public function storeQuiz(Request $request, Document $document): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.manage_quizzes'), 403);

        $this->ensureVisible($document, (string) $user->uuid);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pass_percentage' => ['required', 'integer', 'min:1', 'max:100'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.body' => ['required', 'string'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.options.*' => ['required', 'string'],
            'questions.*.correct_option' => ['required', 'string'],
            'questions.*.points' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($document, $validated, $user): void {
            $quiz = DocumentQuiz::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'settings' => [
                    'pass_percentage' => $validated['pass_percentage'],
                ],
            ]);

            foreach ($validated['questions'] as $idx => $qData) {
                DocumentQuizQuestion::create([
                    'tenant_id' => $document->tenant_id,
                    'quiz_id' => $quiz->id,
                    'body' => $qData['body'],
                    'options' => array_values($qData['options']),
                    'correct_option' => $qData['correct_option'],
                    'points' => (int) ($qData['points'] ?? 1),
                    'sort_order' => $idx + 1,
                ]);
            }

            $this->logAudit($document, 'quiz_created', (string) $user->uuid, [
                'quiz_id' => $quiz->id,
                'title' => $quiz->title,
            ]);
        });

        return redirect()
            ->route('document-management.documents.show', $document)
            ->with('status', 'success')
            ->with('message', 'Comprehension quiz created successfully.');
    }

    public function showQuiz(Request $request, DocumentQuiz $quiz): View
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $document = $quiz->document;
        $this->ensureVisible($document, (string) $user->uuid);

        $quiz->load('questions');

        $attempts = DocumentQuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', (string) $user->uuid)
            ->latest()
            ->get();

        return view('document-management::quizzes.show', [
            'quiz' => $quiz,
            'document' => $document,
            'attempts' => $attempts,
        ]);
    }

    public function attemptQuiz(Request $request, DocumentQuiz $quiz): View|RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('documents.view'), 403);

        $document = $quiz->document;
        $this->ensureVisible($document, (string) $user->uuid);

        $questions = $quiz->questions()->get()->keyBy('id');
        $submittedResponses = (array) $request->input('responses', []);

        $score = 0;
        $maxScore = 0;
        $formattedResponses = [];

        foreach ($questions as $id => $question) {
            $points = (int) ($question->points ?? 1);
            $maxScore += $points;
            $selectedAnswer = $submittedResponses[$id] ?? null;

            $isCorrect = $selectedAnswer !== null && (string) $selectedAnswer === (string) $question->correct_option;
            if ($isCorrect) {
                $score += $points;
            }

            $formattedResponses[] = [
                'question_id' => $id,
                'selected_answer' => $selectedAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        $percentage = $maxScore > 0 ? (int) round(($score / $maxScore) * 100) : 100;
        $passPercentage = (int) ($quiz->settings['pass_percentage'] ?? 80);
        $passed = $percentage >= $passPercentage;

        $attempt = DocumentQuizAttempt::create([
            'tenant_id' => $quiz->tenant_id,
            'quiz_id' => $quiz->id,
            'user_id' => (string) $user->uuid,
            'responses' => $formattedResponses,
            'score' => $score,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->logAudit($document, 'quiz_attempt', (string) $user->uuid, [
            'quiz_id' => $quiz->id,
            'attempt_id' => $attempt->id,
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
        ]);

        return view('document-management::quizzes.result', [
            'quiz' => $quiz,
            'document' => $document,
            'attempt' => $attempt,
            'score' => $score,
            'maxScore' => $maxScore,
            'percentage' => $percentage,
            'passPercentage' => $passPercentage,
            'passed' => $passed,
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

    private function resolveTenantId(?User $user = null): ?string
    {
        $tenantId = session('active_tenant_id')
            ?? app(TenantContext::class)->activeTenantId()
            ?? $user?->tenant_id;

        return $tenantId !== null ? (string) $tenantId : null;
    }

    private function authorizeTenant(Document $document): void
    {
        $tenantId = $this->resolveTenantId(auth()->user());
        if ($tenantId && (string) $document->tenant_id !== (string) $tenantId) {
            abort(404, 'Document not found.');
        }
    }

    private function ensureVisible(Document $document, string $userId): void
    {
        $this->authorizeTenant($document);

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
