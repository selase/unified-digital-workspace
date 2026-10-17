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

                    @if($document->status === 'draft' && $canPublish)
                        <form action="{{ route('document-management.documents.publish', $document) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="kt-btn kt-btn-success flex items-center gap-1.5"
                                onclick="return confirm('Publish this controlled document to the institutional catalog?')"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Publish Document
                            </button>
                        </form>
                    @endif

                    @if($document->status === 'published' && $canUpdate)
                        <form action="{{ route('document-management.documents.archive', $document) }}" method="POST" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="kt-btn kt-btn-outline text-amber-600 dark:text-amber-400 flex items-center gap-1.5"
                                onclick="return confirm('Archive this document? It will be marked superseded/archived.')"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                </svg>
                                Archive
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-outline">
                        Back to Library
                    </a>
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- Status and Metadata Strip --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 rounded-xl border border-border/70 bg-card text-foreground">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Document Status:</span>
                @php
                    $statusVariant = match ($document->status) {
                        'published' => 'success',
                        'archived' => 'warning',
                        default => 'outline',
                    };
                @endphp
                <x-metronic.badge :variant="$statusVariant" size="md">
                    {{ ucfirst($document->status) }}
                </x-metronic.badge>

                @if($document->category)
                    <span class="text-border">|</span>
                    <span class="text-xs font-medium text-muted-foreground">Category:</span>
                    <span class="text-xs font-semibold text-foreground px-2.5 py-1 rounded-md bg-muted/40">
                        {{ $document->category }}
                    </span>
                @endif

                @if($document->currentVersion)
                    <span class="text-border">|</span>
                    <span class="text-xs font-medium text-muted-foreground">Active Version:</span>
                    <x-metronic.badge variant="primary" size="sm">
                        v{{ $document->currentVersion->version_number }}.0
                    </x-metronic.badge>
                @endif
            </div>

            <div class="text-xs text-muted-foreground">
                Document Ref: <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px] text-foreground">{{ $document->slug }}</code>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            {{-- Main Column: Document Details, Active Version, History, and Upload Form --}}
            <div class="lg:col-span-8 flex flex-col gap-5">
                {{-- Document Overview --}}
                <x-metronic.card title="Document Overview & Scope">
                    <div class="space-y-4">
                        @if($document->description)
                            <div>
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Executive Summary</h4>
                                <p class="text-sm leading-relaxed text-foreground whitespace-pre-line">{{ $document->description }}</p>
                            </div>
                        @else
                            <p class="text-sm text-muted-foreground italic">No executive description provided for this document record.</p>
                        @endif

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-border">
                            <div>
                                <p class="text-xs text-muted-foreground">Classification</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ !empty($document->visibility['is_private']) ? 'Restricted / Departmental' : 'Institution-wide' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Owner</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ $document->owner ? $document->owner->first_name.' '.$document->owner->last_name : 'System' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Created</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ $document->created_at?->format('d/m/Y') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Published</p>
                                <p class="text-xs font-medium text-foreground mt-0.5">
                                    {{ $document->published_at ? $document->published_at->format('d/m/Y') : 'Unpublished' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </x-metronic.card>

                {{-- Active Controlled File --}}
                <x-metronic.card title="Master Controlled File" subtitle="Current authorized version in active institutional circulation">
                    @if($document->currentVersion)
                        @php
                            $version = $document->currentVersion;
                            $sizeFormatted = $version->size_bytes > 1048576
                                ? round($version->size_bytes / 1048576, 2) . ' MB'
                                : round($version->size_bytes / 1024, 1) . ' KB';
                        @endphp
                        <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex items-start gap-3.5">
                                    <div class="h-10 w-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-semibold text-foreground">{{ $version->filename }}</h3>
                                            <x-metronic.badge variant="primary" size="sm">
                                                v{{ $version->version_number }}.0 Active
                                            </x-metronic.badge>
                                        </div>
                                        <p class="text-xs text-muted-foreground mt-1">
                                            Size: {{ $sizeFormatted }} &bull; MIME: {{ $version->mime_type ?: 'application/octet-stream' }} &bull; Uploaded {{ $version->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                </div>

                                <a
                                    href="{{ route('document-management.documents.download', ['document' => $document, 'version' => $version->version_number]) }}"
                                    class="kt-btn kt-btn-primary shrink-0 flex items-center gap-1.5"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download
                                </a>
                            </div>

                            @if($version->notes)
                                <div class="mt-3.5 pt-3 border-t border-primary/15 text-xs text-foreground">
                                    <span class="font-semibold text-muted-foreground">Version Notes:</span>
                                    <span class="ml-1 text-muted-foreground">{{ $version->notes }}</span>
                                </div>
                            @endif

                            @if($version->checksum_sha256)
                                <div class="mt-2.5 flex items-center gap-2 text-[11px] text-muted-foreground font-mono bg-background/60 p-2 rounded border border-border">
                                    <span class="font-bold text-foreground shrink-0 uppercase tracking-wider text-[10px]">SHA-256:</span>
                                    <span class="truncate select-all text-muted-foreground">{{ $version->checksum_sha256 }}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="py-8 text-center text-muted-foreground space-y-2">
                            <p class="text-sm">No master file has been uploaded for this document yet.</p>
                            @if($canManageVersions)
                                <p class="text-xs">Use the check-in form below to upload Version 1.0.</p>
                            @endif
                        </div>
                    @endif
                </x-metronic.card>

                {{-- Version History Table --}}
                <x-metronic.card title="Version Control & Change History" subtitle="Immutable revision log for ISO 9001 quality audits">
                    <div class="overflow-x-auto">
                        <x-metronic.table>
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Rev</th>
                                    <th>File Name</th>
                                    <th>Uploaded By</th>
                                    <th>Change Notes</th>
                                    <th>Date</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-foreground divide-y divide-border/60">
                                @forelse($document->versions->sortByDesc('version_number') as $ver)
                                    @php
                                        $isActive = $document->current_version_id === $ver->id;
                                    @endphp
                                    <tr class="{{ $isActive ? 'bg-primary/5 font-medium' : '' }}">
                                        <td>
                                            <div class="flex items-center gap-1.5">
                                                <x-metronic.badge :variant="$isActive ? 'primary' : 'outline'" size="sm">
                                                    v{{ $ver->version_number }}.0
                                                </x-metronic.badge>
                                                @if($isActive)
                                                    <span class="text-[10px] text-primary font-bold uppercase tracking-wider">Active</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <p class="truncate max-w-[180px] font-medium" title="{{ $ver->filename }}">{{ $ver->filename }}</p>
                                            @if($ver->checksum_sha256)
                                                <span class="text-[10px] text-muted-foreground font-mono" title="{{ $ver->checksum_sha256 }}">
                                                    {{ substr($ver->checksum_sha256, 0, 10) }}...
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $ver->uploadedBy ? $ver->uploadedBy->first_name.' '.$ver->uploadedBy->last_name : 'User' }}
                                        </td>
                                        <td>
                                            <p class="max-w-[220px] truncate text-muted-foreground" title="{{ $ver->notes }}">
                                                {{ $ver->notes ?: '—' }}
                                            </p>
                                        </td>
                                        <td class="text-muted-foreground whitespace-nowrap">
                                            {{ $ver->created_at?->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="text-right">
                                            <a
                                                href="{{ route('document-management.documents.download', ['document' => $document, 'version' => $ver->version_number]) }}"
                                                class="kt-btn kt-btn-outline py-1 px-2.5 text-xs inline-flex items-center gap-1"
                                                title="Download version {{ $ver->version_number }}"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-muted-foreground">
                                            No versions recorded yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-metronic.table>
                    </div>
                </x-metronic.card>

                {{-- Check-in New Version Form --}}
                @if($canManageVersions)
                    @php
                        $nextVersionNum = ($document->versions->max('version_number') ?? 0) + 1;
                    @endphp
                    <x-metronic.card title="Check-in New Version (v{{ $nextVersionNum }}.0)" subtitle="Upload an updated revision to supersede the currently active document">
                        <form action="{{ route('document-management.documents.versions.store', $document) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <label for="version_file" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    New Revision File <span class="text-destructive">*</span>
                                </label>
                                <input
                                    type="file"
                                    id="version_file"
                                    name="file"
                                    required
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt"
                                    class="kt-input w-full file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-primary-foreground hover:file:opacity-90"
                                />
                                @error('file')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="version_notes" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
                                    Revision Notes & Changes <span class="text-destructive">*</span>
                                </label>
                                <textarea
                                    id="version_notes"
                                    name="notes"
                                    rows="3"
                                    required
                                    placeholder="Explain the specific changes in this revision, clinical updates, or committee approval references..."
                                    class="kt-textarea w-full text-foreground"
                                >{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    Check In v{{ $nextVersionNum }}.0
                                </button>
                            </div>
                        </form>
                    </x-metronic.card>
                @endif
            </div>

            {{-- Sidebar Column: Quality Status, Document Owner, Training Quizzes, and Audit Trail --}}
            <div class="lg:col-span-4 flex flex-col gap-5">
                {{-- Document Owner & Custodian --}}
                <x-metronic.card title="Document Custodian">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-primary/10 text-primary font-semibold flex items-center justify-center shrink-0">
                            {{ $document->owner ? substr($document->owner->first_name, 0, 1).substr($document->owner->last_name, 0, 1) : 'SY' }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground truncate">
                                {{ $document->owner ? $document->owner->first_name.' '.$document->owner->last_name : 'System Administrator' }}
                            </p>
                            <p class="text-xs text-muted-foreground truncate">
                                {{ $document->owner?->email ?: 'No email on record' }}
                            </p>
                        </div>
                    </div>
                </x-metronic.card>

                {{-- Training Assessments / Quizzes --}}
                <x-metronic.card title="Staff Comprehension & Quizzes">
                    @if($document->quizzes->isNotEmpty())
                        <div class="space-y-2.5">
                            @foreach($document->quizzes as $quiz)
                                <div class="p-3 rounded-lg border border-border bg-muted/20 flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-semibold text-foreground">{{ $quiz->title }}</p>
                                        <p class="text-[11px] text-muted-foreground">Comprehension Assessment</p>
                                    </div>
                                    <span class="kt-badge kt-badge-outline text-[11px]">Active</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-muted-foreground italic">No training comprehension quizzes linked to this document.</p>
                    @endif
                </x-metronic.card>

                {{-- Audit Trail (ISO Compliance Log) --}}
                <x-metronic.card title="Audit & Compliance Trail" subtitle="Chronological record of document access and lifecycle events">
                    <div class="space-y-3 max-h-[420px] overflow-y-auto pr-1">
                        @forelse($document->audits->sortByDesc('created_at')->take(15) as $audit)
                            @php
                                $badgeColor = match ($audit->event) {
                                    'publish' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                    'version_uploaded' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
                                    'download' => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                                    'archive' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                    'create' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                    default => 'bg-muted text-muted-foreground border-border',
                                };
                            @endphp
                            <div class="flex items-start gap-2.5 text-xs">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border shrink-0 mt-0.5 {{ $badgeColor }}">
                                    {{ str_replace('_', ' ', $audit->event) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-foreground font-medium truncate">
                                        {{ $audit->user ? $audit->user->first_name.' '.$audit->user->last_name : 'Automated Task' }}
                                    </p>
                                    <p class="text-[11px] text-muted-foreground">
                                        {{ $audit->created_at?->format('d/m/Y H:i') }} &bull; {{ $audit->created_at?->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-muted-foreground italic">No audit records recorded yet.</p>
                        @endforelse
                    </div>
                </x-metronic.card>
            </div>
        </div>
    </div>
@endsection

