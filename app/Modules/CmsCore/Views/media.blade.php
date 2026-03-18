@extends('layouts.metronic.app')

@section('title', 'CMS Media')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Media Library</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Uploaded assets with metadata, type, size, and ownership.</p>
                </div>
                <div class="flex gap-2">
                    <a class="kt-btn kt-btn-primary" href="{{ route('cms-core.media.create') }}">Upload Media</a>
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">Asset Registry</h3>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('cms-core.media.index') }}">
                    <input
                        class="kt-input w-full min-w-[220px] lg:w-[280px]"
                        name="search"
                        placeholder="Search filename or title"
                        type="text"
                        value="{{ $search }}"
                    />
                    <select class="kt-select min-w-[160px]" name="type">
                        <option value="">All types</option>
                        <option value="image" @selected($type === 'image')>Images</option>
                        <option value="video" @selected($type === 'video')>Videos</option>
                        <option value="application" @selected($type === 'application')>Documents</option>
                        <option value="audio" @selected($type === 'audio')>Audio</option>
                    </select>
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
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
                                        <a href="{{ route('cms-core.media.show', $mediaItem) }}" class="hover:underline">
                                            <p class="font-medium">{{ $mediaItem->title ?: $mediaItem->filename }}</p>
                                            <p class="text-xs text-muted-foreground">{{ $mediaItem->path }}</p>
                                        </a>
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
                                    <td class="py-8 text-center text-muted-foreground" colspan="5">No media records found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $mediaItems->links() }}</div>
            </div>
        </div>
    </section>
@endsection
