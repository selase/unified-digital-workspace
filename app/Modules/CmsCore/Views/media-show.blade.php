@extends('layouts.metronic.app')

@section('title', $media->title ?: $media->filename)

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core / Media</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $media->title ?: $media->original_filename }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $media->mime_type }} &middot; {{ number_format((int) $media->size_bytes / 1024, 2) }} KB</p>
                </div>
                <div class="flex gap-2">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.media.edit', $media) }}">Edit Metadata</a>
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.media.index') }}">Back to Library</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Preview --}}
            <div class="kt-card p-6 lg:col-span-2">
                <h2 class="mb-4 text-sm font-semibold text-foreground">Preview</h2>
                @if(str_starts_with($media->mime_type, 'image/'))
                    <img
                        src="{{ $media->url() }}"
                        alt="{{ $media->alt_text ?? $media->title }}"
                        class="max-h-[500px] w-auto rounded-lg object-contain"
                    />
                @else
                    <div class="flex items-center justify-center rounded-lg bg-muted/30 py-16">
                        <p class="text-muted-foreground">No preview available for {{ $media->mime_type }}</p>
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="kt-card p-6">
                <h2 class="mb-4 text-sm font-semibold text-foreground">Details</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Filename</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $media->original_filename }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Disk</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $media->disk }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Path</dt>
                        <dd class="mt-0.5 break-all font-medium text-foreground">{{ $media->path }}</dd>
                    </div>
                    @if($media->width && $media->height)
                        <div>
                            <dt class="text-muted-foreground">Dimensions</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ $media->width }} &times; {{ $media->height }} px</dd>
                        </div>
                    @endif
                    @if($media->alt_text)
                        <div>
                            <dt class="text-muted-foreground">Alt Text</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ $media->alt_text }}</dd>
                        </div>
                    @endif
                    @if($media->description)
                        <div>
                            <dt class="text-muted-foreground">Description</dt>
                            <dd class="mt-0.5 font-medium text-foreground">{{ $media->description }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-muted-foreground">Visibility</dt>
                        <dd class="mt-0.5">
                            <span class="kt-badge {{ $media->is_public ? 'kt-badge-success' : 'kt-badge-outline' }}">
                                {{ $media->is_public ? 'Public' : 'Private' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Uploaded By</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $media->uploadedBy?->displayName() ?: 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Uploaded At</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $media->created_at?->format('M d, Y H:i') }}</dd>
                    </div>
                </dl>

                <div class="mt-6 border-t border-border pt-4">
                    <form action="{{ route('cms-core.media.destroy', $media) }}" method="POST" onsubmit="return confirm('Delete this media permanently?')">
                        @csrf
                        @method('DELETE')
                        <button class="kt-btn kt-btn-outline text-destructive w-full" type="submit">Delete Media</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection
