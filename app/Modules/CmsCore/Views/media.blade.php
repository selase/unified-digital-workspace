@extends('layouts.metronic.app')

@section('title', 'CMS Media')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Media Library</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Uploaded assets with metadata, type, size, and ownership.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border">
                    <thead>
                    <tr class="text-xs uppercase text-muted-foreground">
                        <th>Asset</th>
                        <th>MIME</th>
                        <th>Size</th>
                        <th>Uploader</th>
                        <th>Visibility</th>
                    </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                    @forelse($mediaItems as $mediaItem)
                        <tr>
                            <td>
                                <p class="font-medium">{{ $mediaItem->title ?: $mediaItem->filename }}</p>
                                <p class="text-xs text-muted-foreground">{{ $mediaItem->path }}</p>
                            </td>
                            <td>{{ $mediaItem->mime_type }}</td>
                            <td>{{ number_format((int) $mediaItem->size_bytes / 1024, 2) }} KB</td>
                            <td>{{ $mediaItem->uploadedBy?->displayName() ?: 'Unknown' }}</td>
                            <td>
                                <span class="kt-badge {{ $mediaItem->is_public ? 'kt-badge-success' : 'kt-badge-outline' }}">
                                    {{ $mediaItem->is_public ? 'Public' : 'Private' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-8 text-center text-muted-foreground" colspan="5">No media records found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $mediaItems->links() }}</div>
        </div>
    </section>
@endsection
