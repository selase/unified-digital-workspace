@extends('layouts.metronic.app')

@section('title', 'Document Management Hub')

@section('content')
    @php
        $draftCount = (int) ($statusCounts['draft'] ?? 0);
        $publishedCount = (int) ($statusCounts['published'] ?? 0);
        $archivedCount = (int) ($statusCounts['archived'] ?? 0);
        $totalDocumentCount = $draftCount + $publishedCount + $archivedCount;
    @endphp

    <div class="grid gap-5">
        <x-metronic.page-header
            title="Document Management Hub"
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Document Management'],
            ]"
        >
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-outline">
                        Browse Library
                    </a>
                    @if($canManageQuizAnalytics)
                        <a href="{{ route('document-management.analytics.index') }}" class="kt-btn kt-btn-outline">
                            Quiz Analytics
                        </a>
                    @endif
                    @can('documents.create')
                        <a href="{{ route('document-management.documents.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Register Document
                        </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-metronic.page-header>

        {{-- KPI Metrics Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Documents</p>
                <p class="mt-2 text-2xl font-bold text-foreground">{{ $totalDocumentCount }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">Controlled inventory</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Published</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $publishedCount }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">In active institutional circulation</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Draft</p>
                <p class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $draftCount }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">Under revision & review</p>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 transition-all hover:border-primary/40 shadow-xs">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Archived</p>
                <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $archivedCount }}</p>
                <p class="mt-1 text-[11px] text-muted-foreground">Superseded historical records</p>
            </div>
        </div>

        {{-- Main Content: Recent Documents and Quiz Snapshot --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <div class="xl:col-span-8">
                <x-metronic.card title="Recent Controlled Documents" subtitle="Latest 10 documents registered or revised">
                    <x-slot:headerActions>
                        <a href="{{ route('document-management.documents.index') }}" class="kt-btn kt-btn-ghost text-xs">
                            View All Library &rarr;
                        </a>
                    </x-slot:headerActions>

                    <div class="overflow-x-auto">
                        <x-metronic.table>
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Category</th>
                                    <th>Version</th>
                                    <th>Updated</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs text-foreground divide-y divide-border/60">
                                @forelse($documents as $document)
                                    @php
                                        $statusVariant = match ($document->status) {
                                            'published' => 'success',
                                            'archived' => 'warning',
                                            default => 'outline',
                                        };
                                    @endphp
                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td>
                                            <div class="flex flex-col">
                                                <a
                                                    href="{{ route('document-management.documents.show', $document) }}"
                                                    class="font-semibold text-foreground hover:text-primary transition-colors text-sm"
                                                >
                                                    {{ $document->title }}
                                                </a>
                                                <span class="text-[11px] text-muted-foreground font-mono">{{ $document->slug }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <x-metronic.badge :variant="$statusVariant" size="sm">
                                                {{ ucfirst($document->status) }}
                                            </x-metronic.badge>
                                        </td>
                                        <td>
                                            <span class="px-2 py-0.5 rounded bg-muted/40 font-medium text-foreground">
                                                {{ $document->category ?: '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $document->currentVersion?->version_number ? 'v'.$document->currentVersion->version_number.'.0' : '—' }}
                                        </td>
                                        <td class="text-muted-foreground whitespace-nowrap">
                                            {{ $document->updated_at?->diffForHumans() }}
                                        </td>
                                        <td class="text-right">
                                            <a
                                                href="{{ route('document-management.documents.show', $document) }}"
                                                class="kt-btn kt-btn-outline py-1 px-2.5 text-xs"
                                            >
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-muted-foreground">
                                            No documents registered yet. Click "Register Document" to add one.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </x-metronic.table>
                    </div>
                </x-metronic.card>
            </div>

            <div class="xl:col-span-4">
                <x-metronic.card title="Comprehension Quizzes" subtitle="Staff training & policy assessments">
                    <div class="space-y-3">
                        @forelse($quizzes as $quiz)
                            <div class="rounded-xl border border-border/80 bg-muted/20 p-3.5 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-foreground text-xs">{{ $quiz['title'] }}</p>
                                        <p class="text-[11px] text-muted-foreground mt-0.5">{{ $quiz['document_title'] ?: 'Unlinked document' }}</p>
                                    </div>
                                    <x-metronic.badge variant="outline" size="sm">
                                        {{ $quiz['attempts_count'] }} attempts
                                    </x-metronic.badge>
                                </div>
                                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-border/50 text-[11px] text-muted-foreground">
                                    <div>
                                        <p>Questions</p>
                                        <p class="font-bold text-foreground">{{ $quiz['questions_count'] }}</p>
                                    </div>
                                    <div>
                                        <p>Participants</p>
                                        <p class="font-bold text-foreground">{{ $quiz['unique_users_count'] }}</p>
                                    </div>
                                    <div>
                                        <p>Avg Score</p>
                                        <p class="font-bold text-foreground">{{ $quiz['average_score'] ?? '—' }}</p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
                                No quizzes created yet.
                            </div>
                        @endforelse
                    </div>
                </x-metronic.card>
            </div>
        </div>
    </div>
@endsection
