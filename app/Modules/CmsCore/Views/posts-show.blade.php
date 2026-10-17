@extends('layouts.metronic.app')

@section('title', $post->title)

@section('content')
@php
    $previewRoute = $post->postType?->slug === 'page'
        ? route('cms-core.public.pages.show', $post->slug)
        : route('cms-core.public.posts.show', $post->slug);

    $statusBadgeClass = match ($post->status) {
        'published' => 'kt-badge-success kt-badge-outline',
        'scheduled' => 'kt-badge-warning kt-badge-outline',
        'archived' => 'kt-badge-danger kt-badge-outline',
        default => 'kt-badge-outline',
    };
@endphp

<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="{{ $post->title }}"
        description="Slug: {{ $post->slug }} · Status: {{ ucfirst($post->status) }}"
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">
                Back to Library
            </a>
            <a class="kt-btn kt-btn-outline flex items-center gap-1.5" href="{{ $previewRoute }}" target="_blank">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                Preview
            </a>
            @can('cms.posts.update')
                @if($post->status !== 'published')
                    <form action="{{ route('cms-core.posts.publish', $post) }}" method="POST" class="inline-block">
                        @csrf
                        <button class="kt-btn kt-btn-success flex items-center gap-1.5" type="submit">
                            <i class="ki-filled ki-check-circle text-base"></i>
                            Publish Now
                        </button>
                    </form>
                @else
                    <form action="{{ route('cms-core.posts.archive', $post) }}" method="POST" class="inline-block">
                        @csrf
                        <button class="kt-btn kt-btn-outline text-amber-600 hover:bg-amber-500/10" onclick="return confirm('Archive this post?');" type="submit">
                            Archive
                        </button>
                    </form>
                @endif
                <a class="kt-btn kt-btn-primary flex items-center gap-1.5" href="{{ route('cms-core.posts.edit', $post) }}">
                    <i class="ki-filled ki-notepad-edit text-base"></i>
                    Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Main Show Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left: Article Narrative & Revisions (8 cols) --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            <x-metronic.card title="Content">
                {{-- Featured Image if any --}}
                @if($post->featuredMedia)
                    <div class="mb-6 overflow-hidden rounded-xl border border-border bg-muted/20">
                        <img
                            src="{{ $post->featuredMedia->url() }}"
                            alt="{{ $post->featuredMedia->alt_text ?: $post->title }}"
                            class="w-full max-h-[380px] object-cover"
                        />
                        @if($post->featuredMedia->caption)
                            <div class="p-2.5 text-center text-xs text-muted-foreground italic bg-muted/30 border-t border-border">
                                {{ $post->featuredMedia->caption }}
                            </div>
                        @endif
                    </div>
                @endif

                @if($post->excerpt)
                    <div class="mb-6 rounded-lg border-l-4 border-primary bg-muted/30 p-4 text-sm font-medium text-foreground">
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold mb-1">Executive Summary / Excerpt</p>
                        <p class="mt-4 text-sm text-muted-foreground">{{ $post->excerpt }}</p>
                    </div>
                @endif

                <div class="prose max-w-none text-sm text-foreground leading-relaxed mt-4">
                    {!! $post->body !!}
                </div>
            </x-metronic.card>

            {{-- Revisions if any --}}
            @if($post->revisions->isNotEmpty())
                <x-metronic.card title="Editorial Revisions ({{ $post->revisions->count() }})">
                    <div class="divide-y divide-border">
                        @foreach($post->revisions as $revision)
                            <div class="py-3 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-medium text-foreground">{{ $revision->title }}</span>
                                    <span class="text-muted-foreground ml-2">Revision #{{ $revision->id }}</span>
                                </div>
                                <span class="text-muted-foreground">{{ $revision->created_at?->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-metronic.card>
            @endif
        </div>

        {{-- Right: Publishing Metadata & Governance (4 cols) --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="Publishing Metadata">
                <div class="space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border">
                        <span class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Status</span>
                        <span class="kt-badge {{ $statusBadgeClass }} text-xs">
                            {{ ucfirst($post->status) }}
                        </span>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Post Type</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ $post->postType?->name ?: 'Standard Post' }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Author</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ $post->author?->displayName() ?: 'Unknown Author' }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Editor</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ $post->editor?->displayName() ?: 'Not specified' }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Published At</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ $post->published_at?->format('d/m/Y H:i') ?: 'Not published' }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Scheduled For</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ $post->scheduled_for?->format('d/m/Y H:i') ?: 'Not scheduled' }}</p>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Last Modified</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ $post->updated_at?->format('d/m/Y H:i') }}</p>
                    </div>
                </div>

                {{-- Categories --}}
                <div class="mt-6 pt-4 border-t border-border">
                    <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold mb-2">Categories</p>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($post->categories as $category)
                            <span class="kt-badge kt-badge-outline text-xs">{{ $category->name }}</span>
                        @empty
                            <span class="text-sm text-muted-foreground">No categories assigned.</span>
                        @endforelse
                    </div>
                </div>

                {{-- Tags --}}
                <div class="mt-4 pt-4 border-t border-border">
                    <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold mb-2">Tags</p>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($post->tags as $tag)
                            <span class="kt-badge kt-badge-outline text-xs">#{{ $tag->name }}</span>
                        @empty
                            <span class="text-sm text-muted-foreground">No tags assigned.</span>
                        @endforelse
                    </div>
                </div>

                {{-- Featured Media Info --}}
                <div class="mt-4 pt-4 border-t border-border">
                    <p class="text-xs uppercase tracking-wider text-muted-foreground font-semibold mb-2">Featured Media</p>
                    @if($post->featuredMedia)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="kt-badge kt-badge-outline">
                                {{ $post->featuredMedia->title ?: $post->featuredMedia->original_filename ?: $post->featuredMedia->filename }}
                            </span>
                        </div>
                    @else
                        <span class="text-sm text-muted-foreground">No featured media selected.</span>
                    @endif
                </div>
            </x-metronic.card>
        </div>
    </div>
</div>
@endsection
