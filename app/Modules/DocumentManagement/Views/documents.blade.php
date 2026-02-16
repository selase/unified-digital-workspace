@extends('layouts.metronic.app')

@section('title', 'Documents')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Document Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Document Library</h1>
                    <p class="mt-2 text-sm text-muted-foreground">All controlled documents with version visibility and publication status.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('document-management.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>Title</th>
                            <th>Status</th>
                            <th>Version</th>
                            <th>Category</th>
                            <th>Updated</th>
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
                                    <p class="font-medium">{{ $document->title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $document->slug }}</p>
                                </td>
                                <td><span class="kt-badge {{ $statusClass }}">{{ ucfirst($document->status) }}</span></td>
                                <td>{{ $document->currentVersion?->version_number ? 'v'.$document->currentVersion->version_number : '—' }}</td>
                                <td>{{ $document->category ?: '—' }}</td>
                                <td>{{ $document->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-8 text-center text-muted-foreground" colspan="5">No documents found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $documents->links() }}</div>
        </div>
    </section>
@endsection
