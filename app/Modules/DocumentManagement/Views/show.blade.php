@extends('layouts.metronic.app')

@section('title', $document->title)

@section('content')
    <div class="grid gap-5">
        {{-- Page Header --}}
        <x-metronic.page-header
            :title="$document->title"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management', 'url' => route('document-management.index')],
                ['label' => 'Controlled Library', 'url' => route('document-management.documents.index')],
                ['label' => $document->slug],
            ]"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    @if($document->currentVersion)
                        <a
                            href="{{ route('document-management.documents.download', ['document' => $document]) }}"
                            class="kt-btn kt-btn-primary flex items-center gap-1.5"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download Master (v{{ $document->currentVersion->version_number }})
                        </a>
                    @endif

                    @if($canUpdate)
                        <a href="{{ route('document-management.documents.edit', $document) }}" class="kt-btn kt-btn-outline flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit Specifications
                        </a>
                    @endif

                    @if($canDelete)
                        <form action="{{ route('document-management.documents.destroy', $document) }}" method="POST" class="inline" onsubmit="return confirm('Soft-delete this controlled document from the catalog?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="kt-btn kt-btn-outline text-destructive hover:bg-destructive/10">
                                Delete
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-outline">
                        Back to Library
                    </a>
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- ISO 9001 6-Stage Lifecycle Pipeline Header Strip --}}
        <div class="kt-card p-5 bg-card border border-border">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-border pb-4 mb-4">
                <div>
                    <span class="text-xs uppercase tracking-wider font-semibold text-muted-foreground">Controlled Document Lifecycle</span>
                    <h2 class="text-lg font-bold text-foreground flex items-center gap-2">
                        <span>{{ $document->title }}</span>
                        @php
                            $badgeMap = [
                                'draft' => 'kt-badge-secondary',
                                'in_review' => 'kt-badge-warning',
                                'approved' => 'kt-badge-primary',
                                'published' => 'kt-badge-success',
                                'under_revision' => 'kt-badge-warning',
                                'obsolete' => 'kt-badge-destructive',
                                'archived' => 'kt-badge-outline',
                            ];
                        @endphp
                        <span class="kt-badge {{ $badgeMap[$document->status] ?? 'kt-badge-outline' }} text-xs px-2.5 py-0.5">
                            {{ \App\Modules\DocumentManagement\Models\Document::STATUS_LABELS[$document->status] ?? ucfirst($document->status) }}
                        </span>
                    </h2>
                </div>

                {{-- Key Dates & Review Indicators --}}
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    @if($document->review_due_at)
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border {{ $document->isReviewOverdue() ? 'bg-destructive/10 border-destructive/30 text-destructive' : 'bg-muted/30 border-border text-foreground' }}">
                            <span class="font-semibold">Review Due:</span>
                            <span>{{ $document->review_due_at->format('d/m/Y') }}</span>
                            @if($document->isReviewOverdue())
                                <span class="kt-badge kt-badge-2xs kt-badge-destructive">OVERDUE</span>
                            @endif
                        </div>
                    @endif

                    @if($document->expires_at)
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border {{ $document->isExpired() ? 'bg-destructive/10 border-destructive/30 text-destructive' : 'bg-muted/30 border-border text-foreground' }}">
                            <span class="font-semibold">Expires:</span>
                            <span>{{ $document->expires_at->format('d/m/Y') }}</span>
                            @if($document->isExpired())
                                <span class="kt-badge kt-badge-2xs kt-badge-destructive">EXPIRED</span>
                            @endif
                        </div>
                    @endif

                    @if($document->status === 'published' && ! ($billingOwnScope ?? false))
                        <a href="{{ route('document-management.documents.compliance', $document) }}" class="kt-btn kt-btn-xs kt-btn-outline flex items-center gap-1">
                            <span>Compliance: {{ $ackPercentage }}%</span>
                            <span class="text-muted-foreground font-normal">({{ $ackCount }}/{{ $tenantUsersCount }})</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- 6-Stage Visual Stepper --}}
            @php
                $stages = [
                    'draft' => '1. Draft',
                    'in_review' => '2. In Review',
                    'approved' => '3. Approved',
                    'published' => '4. Published',
                    'under_revision' => '5. Revision',
                    'obsolete' => '6. Obsolete',
                ];
                $activeIdx = array_search($document->status, array_keys($stages));
                if ($activeIdx === false && $document->status === 'archived') {
                    $activeIdx = 5;
                }
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2">
                @foreach($stages as $key => $label)
                    @php
                        $isCurrent = $document->status === $key;
                    @endphp
                    <div class="p-2.5 rounded-lg border text-center text-xs transition-colors {{ $isCurrent ? 'bg-primary/10 border-primary text-primary font-bold shadow-sm' : 'bg-muted/10 border-border text-muted-foreground' }}">
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Main Two-Column Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {{-- Left: Details, Versions, Quizzes, Audits --}}
            <div class="lg:col-span-8 flex flex-col gap-5">
                {{-- ISO Stage Action Controls Banner --}}
                <div class="kt-card p-5 bg-card border border-border">
                    <h3 class="text-sm font-semibold text-foreground mb-3 flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Lifecycle Actions & Workflow Transitions
                    </h3>

                    <div class="flex flex-wrap items-center gap-3">
                        {{-- Submit For Review --}}
                        @if(in_array($document->status, ['draft', 'under_revision'], true))
                            <form action="{{ route('document-management.documents.submit-review', $document) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-warning flex items-center gap-1.5" onclick="return confirm('Submit this document for technical & peer review?');">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Submit for ISO Review
                                </button>
                            </form>
                        @endif

                        {{-- Approve Review --}}
                        @if($document->status === 'in_review' && $canApprove)
                            <form action="{{ route('document-management.documents.approve', $document) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary flex items-center gap-1.5" onclick="return confirm('Approve this controlled document?');">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Approve Document
                                </button>
                            </form>
                        @endif

                        {{-- Reject Review --}}
                        @if($document->status === 'in_review' && ($canReview || $canApprove))
                            <button type="button" class="kt-btn kt-btn-sm kt-btn-outline text-destructive" onclick="document.getElementById('reject-form-modal').classList.toggle('hidden')">
                                Request Revision (Reject)
                            </button>
                        @endif

                        {{-- Publish --}}
                        @if(in_array($document->status, ['draft', 'approved'], true) && $canPublish)
                            <form action="{{ route('document-management.documents.publish', $document) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-success flex items-center gap-1.5" onclick="return confirm('Publish this document to institutional catalog?');">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Publish to Catalog
                                </button>
                            </form>
                        @endif

                        {{-- Revise --}}
                        @if($document->status === 'published' && $canUpdate)
                            <form action="{{ route('document-management.documents.revise', $document) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline text-amber-600 dark:text-amber-400" onclick="return confirm('Initiate document revision? Status will move to Under Revision.');">
                                    Initiate Revision
                                </button>
                            </form>
                        @endif

                        {{-- Mark Obsolete --}}
                        @if(in_array($document->status, ['published', 'under_revision'], true) && $canUpdate)
                            <form action="{{ route('document-management.documents.obsolete', $document) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline text-muted-foreground" onclick="return confirm('Retire this document as Obsolete/Superseded?');">
                                    Mark Obsolete
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Collapsible Rejection Form --}}
                    <div id="reject-form-modal" class="hidden mt-4 pt-4 border-t border-border">
                        <form action="{{ route('document-management.documents.reject', $document) }}" method="POST" class="space-y-3">
                            @csrf
                            <label class="block text-xs font-semibold text-foreground">Review Feedback & Required Changes *</label>
                            <textarea name="review_comments" rows="3" required class="kt-textarea w-full text-xs" placeholder="Detail mandatory corrections before approval..."></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="kt-btn kt-btn-xs kt-btn-outline" onclick="document.getElementById('reject-form-modal').classList.add('hidden')">Cancel</button>
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-destructive">Confirm Return for Revision</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Document Scope & Description --}}
                <x-metronic.card title="Executive Summary & Applicability" subtitle="Scope, clinical guidelines, and standard operating instructions">
                    <div class="text-sm text-foreground leading-relaxed whitespace-pre-line">
                        {{ $document->description ?: 'No operational description or summary provided for this controlled document.' }}
                    </div>
                </x-metronic.card>

                {{-- Read Acknowledgement Action Strip (For Published Documents) --}}
                @if($document->status === 'published')
                    <div class="kt-card p-5 border border-border bg-card">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-sm text-foreground flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    ISO Read Acknowledgement & Compliance
                                </h3>
                                <p class="text-xs text-muted-foreground mt-0.5">
                                    Employees must confirm they have read, understood, and agree to adhere to this standard.
                                </p>
                            </div>

                            <div>
                                @if($isAcknowledged)
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-success/10 text-success text-xs font-semibold border border-success/20">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Read Acknowledged
                                    </div>
                                @else
                                    <form action="{{ route('document-management.documents.acknowledge', $document) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary flex items-center gap-1.5">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Acknowledge Read & Understood
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Controlled File Versions --}}
                <x-metronic.card title="Controlled Version History" subtitle="Immutable cryptographic releases, release notes, and master downloads">
                    <div class="space-y-4">
                        <div class="overflow-x-auto">
                            <table class="kt-table table-auto kt-table-border w-full text-xs">
                                <thead>
                                    <tr class="border-b border-border text-muted-foreground font-semibold">
                                        <th class="py-2 text-start">Ver.</th>
                                        <th class="py-2 text-start">File Name</th>
                                        <th class="py-2 text-start">Size</th>
                                        <th class="py-2 text-start">Uploaded By</th>
                                        <th class="py-2 text-start">Released</th>
                                        <th class="py-2 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @forelse($document->versions as $ver)
                                        <tr>
                                            <td class="py-2.5 font-bold text-foreground">
                                                v{{ $ver->version_number }}
                                                @if((int) $document->current_version_id === (int) $ver->id)
                                                    <span class="kt-badge kt-badge-2xs kt-badge-primary ml-1">Current</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-foreground">
                                                <div>{{ $ver->filename }}</div>
                                                @if($ver->notes)
                                                    <div class="text-2xs text-muted-foreground italic">{{ $ver->notes }}</div>
                                                @endif
                                                @if($ver->checksum_sha256)
                                                    <div class="text-2xs text-muted-foreground font-mono truncate max-w-xs" title="SHA-256: {{ $ver->checksum_sha256 }}">
                                                        SHA256: {{ substr($ver->checksum_sha256, 0, 16) }}...
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="py-2.5 text-muted-foreground">{{ number_format($ver->size_bytes / 1024, 1) }} KB</td>
                                            <td class="py-2.5 text-muted-foreground">{{ $ver->uploadedBy?->name ?? '—' }}</td>
                                            <td class="py-2.5 text-muted-foreground">{{ $ver->created_at?->format('d/m/Y H:i') }}</td>
                                            <td class="py-2.5 text-end">
                                                <a href="{{ route('document-management.documents.download', ['document' => $document, 'version' => $ver->version_number]) }}" class="kt-btn kt-btn-2xs kt-btn-outline">
                                                    Download
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted-foreground">No file versions uploaded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Upload New Version Form --}}
                        @if($canManageVersions)
                            <div class="pt-4 border-t border-border">
                                <h4 class="text-xs font-semibold text-foreground mb-3 uppercase tracking-wider">Release New Version</h4>
                                <form action="{{ route('document-management.documents.versions.store', $document) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                                    @csrf
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-2xs font-semibold text-muted-foreground mb-1">New File *</label>
                                            <input type="file" name="file" required class="kt-input w-full text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-2xs font-semibold text-muted-foreground mb-1">Release Revision Notes *</label>
                                            <input type="text" name="notes" required placeholder="e.g. Annual clinical audit updates..." class="kt-input w-full text-xs" />
                                        </div>
                                    </div>
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Upload & Bump Version</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </x-metronic.card>

                {{-- Comprehension Quizzes --}}
                <x-metronic.card title="Comprehension Quizzes & Staff Assessments" subtitle="Verify clinical understanding and staff training readiness">
                    <div class="space-y-4">
                        @if($document->quizzes->isNotEmpty())
                            <div class="divide-y divide-border">
                                @foreach($document->quizzes as $quiz)
                                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <h4 class="font-semibold text-sm text-foreground">{{ $quiz->title }}</h4>
                                            <p class="text-xs text-muted-foreground">{{ $quiz->description ?: 'Mandatory verification quiz.' }}</p>
                                            <div class="flex items-center gap-2 mt-1 text-2xs text-muted-foreground">
                                                <span>{{ $quiz->questions->count() }} question(s)</span>
                                                <span>&bull;</span>
                                                <span>Pass standard: {{ $quiz->settings['pass_percentage'] ?? 80 }}%</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('document-management.quizzes.show', $quiz) }}" class="kt-btn kt-btn-xs kt-btn-primary">
                                                Take Quiz
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-muted-foreground py-2">No comprehension quizzes currently attached to this document.</p>
                        @endif

                        @if($canManageQuizzes)
                            <div class="pt-3 border-t border-border flex justify-end">
                                <a href="{{ route('document-management.documents.quizzes.create', $document) }}" class="kt-btn kt-btn-xs kt-btn-outline flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Comprehension Quiz
                                </a>
                            </div>
                        @endif
                    </div>
                </x-metronic.card>

                {{-- Chronological ISO Audit History Trail --}}
                <x-metronic.card title="ISO Chronological Audit Trail" subtitle="Comprehensive record of reviews, approvals, downloads, and lifecycle transitions">
                    <div class="overflow-x-auto">
                        <table class="kt-table table-auto kt-table-border w-full text-xs">
                            <thead>
                                <tr class="border-b border-border text-muted-foreground font-semibold">
                                    <th class="py-2 text-start">Timestamp</th>
                                    <th class="py-2 text-start">Event</th>
                                    <th class="py-2 text-start">User</th>
                                    <th class="py-2 text-start">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse($document->audits as $audit)
                                    <tr>
                                        <td class="py-2 text-muted-foreground">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</td>
                                        <td class="py-2 font-semibold text-foreground">
                                            <span class="kt-badge kt-badge-2xs kt-badge-outline uppercase">
                                                {{ str_replace('_', ' ', $audit->event) }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-foreground">{{ $audit->user?->name ?? 'System' }}</td>
                                        <td class="py-2 text-muted-foreground text-2xs truncate max-w-xs">
                                            @if(!empty($audit->metadata))
                                                {{ json_encode($audit->metadata) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted-foreground">No audit entries recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-metronic.card>
            </div>

            {{-- Right: Governance, Reviewers, Dates --}}
            <div class="lg:col-span-4 flex flex-col gap-5">
                <x-metronic.card title="Governance & Ownership" subtitle="Accountable staff and workflow assignments">
                    <div class="space-y-4 text-xs">
                        <div>
                            <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Document Author / Custodian</span>
                            <span class="font-semibold text-foreground block text-sm">{{ $document->owner?->name ?? 'Unassigned' }}</span>
                            <span class="text-muted-foreground text-2xs">{{ $document->owner?->email }}</span>
                        </div>

                        <hr class="border-border" />

                        <div>
                            <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Designated Reviewer</span>
                            <span class="font-semibold text-foreground block">{{ $document->reviewer?->name ?? 'None Designated' }}</span>
                            <span class="text-muted-foreground text-2xs">{{ $document->reviewer?->email }}</span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Institutional Approver</span>
                            <span class="font-semibold text-foreground block">{{ $document->approver?->name ?? 'None Designated' }}</span>
                            <span class="text-muted-foreground text-2xs">{{ $document->approver?->email }}</span>
                        </div>

                        @if($document->review_comments)
                            <div class="p-3 rounded-lg bg-muted/20 border border-border">
                                <span class="font-semibold text-foreground block text-2xs uppercase tracking-wider mb-1">Review Feedback Notes:</span>
                                <p class="text-muted-foreground text-xs italic">{{ $document->review_comments }}</p>
                            </div>
                        @endif

                        <hr class="border-border" />

                        @if($document->supersedes)
                            <div>
                                <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Supersedes Previous Policy:</span>
                                <a href="{{ route('document-management.documents.show', $document->supersedes) }}" class="text-primary hover:underline font-semibold block">
                                    {{ $document->supersedes->title }} ({{ $document->supersedes->slug }})
                                </a>
                            </div>
                        @endif

                        @if($document->supersededBy)
                            <div>
                                <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Superseded By Active Master:</span>
                                <a href="{{ route('document-management.documents.show', $document->supersededBy) }}" class="text-primary hover:underline font-semibold block">
                                    {{ $document->supersededBy->title }} ({{ $document->supersededBy->slug }})
                                </a>
                            </div>
                        @endif

                        <div>
                            <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Classification & Category</span>
                            <span class="text-foreground font-semibold">{{ $document->category ?? 'General' }}</span>
                        </div>

                        <div>
                            <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Registered Date</span>
                            <span class="text-foreground">{{ $document->created_at?->format('d/m/Y H:i') }}</span>
                        </div>

                        @if($document->published_at)
                            <div>
                                <span class="text-muted-foreground block text-2xs uppercase tracking-wider font-semibold">Effective / Catalog Date</span>
                                <span class="text-foreground">{{ $document->published_at->format('d/m/Y') }}</span>
                            </div>
                        @endif
                    </div>
                </x-metronic.card>
            </div>
        </div>
    </div>
@endsection
