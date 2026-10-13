@extends('layouts.metronic.app')

@section('title', 'CMS Hub')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Website content</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">CMS Hub</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Everything published on your website: pages, news, homepage slides and media.</p>
                </div>
                @if($canCreate)
                    <a href="{{ route('cms-core.posts.create') }}" class="kt-btn kt-btn-primary">New content</a>
                @endif
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @forelse($contentTypes as $contentType)
                    <a href="{{ route('cms-core.posts.index', ['post_type_id' => $contentType->id]) }}" class="rounded-lg bg-muted/30 p-4 transition hover:bg-muted/50">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ $contentType->name }}</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">{{ $contentType->posts_count }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $contentType->published_posts_count }} published</p>
                    </a>
                @empty
                    <p class="text-sm text-muted-foreground md:col-span-2 xl:col-span-4">No content types have been set up yet.</p>
                @endforelse

                @if($mediaCount !== null)
                    <a href="{{ route('cms-core.media.index') }}" class="rounded-lg bg-muted/30 p-4 transition hover:bg-muted/50">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Media files</p>
                        <p class="mt-2 text-xl font-semibold text-foreground">{{ $mediaCount }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Images and documents</p>
                    </a>
                @endif
            </div>
        </div>

        <div class="kt-card p-6">
            <h2 class="mb-4 text-lg font-semibold text-foreground">What would you like to do?</h2>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($shortcuts as $shortcut)
                    <a href="{{ $shortcut['url'] }}" class="rounded-lg bg-muted/30 p-4 transition hover:bg-muted/50">
                        <p class="font-medium text-foreground">{{ $shortcut['label'] }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $shortcut['hint'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>

        @if($apiLinks->isNotEmpty())
            <div class="kt-card p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-foreground">For developers</h2>
                    <span class="text-xs text-muted-foreground">Versioned API endpoints</span>
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($apiLinks as $apiLink)
                        <a href="{{ $apiLink['url'] }}" class="rounded-lg bg-muted/30 p-4 transition hover:bg-muted/50">
                            <p class="font-medium text-foreground">{{ $apiLink['label'] }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $apiLink['route'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endsection
