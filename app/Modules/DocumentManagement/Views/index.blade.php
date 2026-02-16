@extends('layouts.metronic.app')

@section('title', 'Document Management Hub')

@section('content')
    @php
        $draftCount = (int) ($statusCounts['draft'] ?? 0);
        $publishedCount = (int) ($statusCounts['published'] ?? 0);
        $archivedCount = (int) ($statusCounts['archived'] ?? 0);
        $totalDocumentCount = $draftCount + $publishedCount + $archivedCount;
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Document Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Document Management Hub</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Manage controlled documents, revisions, and learning quizzes.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($canManageQuizAnalytics)
                        <a href="{{ route('document-management.analytics.index') }}" class="kt-btn kt-btn-primary">
                            Quiz Analytics
                        </a>
                    @endif
                    <a href="{{ route('api.document-management.v1.documents.index') }}" class="kt-btn kt-btn-outline">Open API</a>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Documents</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $totalDocumentCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Draft</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $draftCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Published</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $publishedCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Archived</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $archivedCount }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-8">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-foreground">Recent Documents</h2>
                    <span class="text-xs text-muted-foreground">Latest 10 updates</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="kt-table">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Title</th>
                                <th>Status</th>
                                <th>Category</th>
                                <th>Current Version</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($documents as $document)
                                @php
                                    $statusClass = match ($document->status) {
                                        'published' => 'kt-badge-success',
                                        'archived' => 'kt-badge-warning',
                                        default => 'kt-badge-outline',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ $document->title }}</span>
                                            <span class="text-xs text-muted-foreground">{{ $document->slug }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="kt-badge {{ $statusClass }}">{{ ucfirst($document->status) }}</span>
                                    </td>
                                    <td>{{ $document->category ?: '—' }}</td>
                                    <td>{{ $document->currentVersion?->version_number ? 'v'.$document->currentVersion->version_number : '—' }}</td>
                                    <td>{{ $document->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">No documents available yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-border bg-background p-6 xl:col-span-4">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-foreground">Quiz Snapshot</h2>
                    <span class="text-xs text-muted-foreground">Latest 8 quizzes</span>
                </div>
                <div class="space-y-3">
                    @forelse($quizzes as $quiz)
                        <div class="rounded-lg border border-border p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-foreground">{{ $quiz['title'] }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $quiz['document_title'] ?: 'Unlinked document' }}</p>
                                </div>
                                <span class="kt-badge kt-badge-outline">{{ $quiz['attempts_count'] }} attempts</span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                                <div>
                                    <p>Questions</p>
                                    <p class="mt-1 text-sm font-semibold text-foreground">{{ $quiz['questions_count'] }}</p>
                                </div>
                                <div>
                                    <p>Participants</p>
                                    <p class="mt-1 text-sm font-semibold text-foreground">{{ $quiz['unique_users_count'] }}</p>
                                </div>
                                <div>
                                    <p>Avg Score</p>
                                    <p class="mt-1 text-sm font-semibold text-foreground">{{ $quiz['average_score'] ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No quizzes configured yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
