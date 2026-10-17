@extends('layouts.metronic.app')

@section('title', 'CMS Hub')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="CMS Hub"
        description="Everything published on your website: pages, news, homepage slides, media, and structured taxonomy."
    >
        <x-slot:actions>
            <a href="{{ route('cms-core.posts.index') }}" class="kt-btn kt-btn-outline">
                All Content
            </a>
            @can('cms.categories.view')
                <a href="{{ route('cms-core.categories.index') }}" class="kt-btn kt-btn-outline">
                    Categories
                </a>
            @endcan
            @can('cms.media.view')
                <a href="{{ route('cms-core.media.index') }}" class="kt-btn kt-btn-outline">
                    Media Library
                </a>
            @endcan
            @if($canCreate)
                <a href="{{ route('cms-core.posts.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <i class="ki-filled ki-plus text-base"></i>
                    New Content
                </a>
            @endif
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

    {{-- Top Metrics: 5 Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Content</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['total_posts'] ?? 0 }}</span>
                <span class="text-[11px] text-muted-foreground">All items</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Published</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $metrics['published_posts'] ?? 0 }}</span>
                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Live on site</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Drafts</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $metrics['draft_posts'] ?? 0 }}</span>
                <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">In preparation</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Scheduled</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-sky-600 dark:text-sky-400">{{ $metrics['scheduled_posts'] ?? 0 }}</span>
                <span class="text-[11px] text-sky-600 dark:text-sky-400 font-medium">Queue</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Taxonomy</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $metrics['categories_count'] ?? 0 }}</span>
                <span class="text-[11px] text-muted-foreground">Categories</span>
            </div>
        </div>
    </div>

    {{-- Main Workspace Split --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left Column: Recent Posts & Content Types --}}
        <div class="lg:col-span-8 flex flex-col gap-6">
            {{-- Recent Posts Table --}}
            <x-metronic.card title="Recent Content Entries">
                <x-slot:toolbar>
                    <a href="{{ route('cms-core.posts.index') }}" class="text-xs font-medium text-primary hover:underline flex items-center gap-1">
                        View post library &rarr;
                    </a>
                </x-slot:toolbar>

                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border align-middle w-full text-sm">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                                <th class="py-3 px-4 font-semibold text-start">Title & Slug</th>
                                <th class="py-3 px-4 font-semibold text-start">Type</th>
                                <th class="py-3 px-4 font-semibold text-start">Author</th>
                                <th class="py-3 px-4 font-semibold text-start">Status</th>
                                <th class="py-3 px-4 font-semibold text-start">Updated</th>
                                <th class="py-3 px-4 font-semibold text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($recentPosts as $post)
                                <tr class="hover:bg-muted/10 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="flex flex-col">
                                            <a href="{{ route('cms-core.posts.show', $post) }}" class="font-medium text-foreground hover:text-primary transition-colors">
                                                {{ $post->title }}
                                            </a>
                                            <span class="text-xs text-muted-foreground font-mono">{{ $post->slug }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="kt-badge kt-badge-outline text-xs">
                                            {{ $post->postType?->name ?: 'Post' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-muted-foreground">
                                        {{ $post->author?->displayName() ?: 'Editorial Team' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        @php
                                            $statusBadgeClass = match($post->status) {
                                                'published' => 'kt-badge-success kt-badge-outline',
                                                'scheduled' => 'kt-badge-warning kt-badge-outline',
                                                'archived' => 'kt-badge-danger kt-badge-outline',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $statusBadgeClass }} text-xs">
                                            {{ ucfirst($post->status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-muted-foreground">
                                        {{ $post->updated_at?->diffForHumans() }}
                                    </td>
                                    <td class="py-3 px-4 text-end">
                                        <a href="{{ route('cms-core.posts.show', $post) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                            Manage
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        No content entries published yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-metronic.card>

            {{-- Content Types Breakdown --}}
            <x-metronic.card title="Content Types & Portals">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse($contentTypes as $contentType)
                        <a href="{{ route('cms-core.posts.index', ['post_type_id' => $contentType->id]) }}" class="group rounded-xl border border-border bg-card p-4 transition hover:border-primary/40 hover:bg-muted/30">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground group-hover:text-primary transition-colors">{{ $contentType->name }}</span>
                                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $contentType->published_posts_count }} live</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-xl font-bold text-foreground">{{ $contentType->posts_count }}</span>
                                <span class="text-xs text-muted-foreground">Total items</span>
                            </div>
                            @if($contentType->description)
                                <p class="mt-2 text-xs text-muted-foreground line-clamp-1">{{ $contentType->description }}</p>
                            @endif
                        </a>
                    @empty
                        <p class="text-sm text-muted-foreground col-span-full">No active content types registered.</p>
                    @endforelse

                    @if($mediaCount !== null)
                        <a href="{{ route('cms-core.media.index') }}" class="group rounded-xl border border-border bg-card p-4 transition hover:border-primary/40 hover:bg-muted/30">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground group-hover:text-primary transition-colors">Media library</span>
                                <span class="text-xs font-medium text-primary">Controlled</span>
                            </div>
                            <div class="mt-3 flex items-baseline justify-between">
                                <span class="text-xl font-bold text-foreground">{{ $mediaCount }}</span>
                                <span class="text-xs text-muted-foreground">Images & docs</span>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">Upload, organize, and serve optimized digital assets.</p>
                        </a>
                    @endif
                </div>
            </x-metronic.card>
        </div>

        {{-- Right Column: What would you like to do? & Developers --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            <x-metronic.card title="What would you like to do?">
                <div class="flex flex-col gap-2.5">
                    @foreach($shortcuts as $shortcut)
                        <a href="{{ $shortcut['url'] }}" class="group flex items-start gap-3 rounded-lg border border-border/60 bg-muted/20 p-3 transition hover:border-primary/40 hover:bg-muted/50">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-foreground transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-foreground group-hover:text-primary transition-colors">{{ $shortcut['label'] }}</span>
                                <span class="text-xs text-muted-foreground">{{ $shortcut['hint'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </x-metronic.card>

            @if($apiLinks->isNotEmpty())
                <x-metronic.card title="For developers">
                    <x-slot:toolbar>
                        <span class="text-xs text-muted-foreground">REST API</span>
                    </x-slot:toolbar>
                    <p class="text-xs text-muted-foreground mb-3">Institutional endpoints for programmatic content delivery and syndication.</p>
                    <div class="flex flex-col gap-2">
                        @foreach($apiLinks as $apiLink)
                            <a href="{{ $apiLink['url'] }}" class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 px-3 py-2 text-xs font-mono text-muted-foreground hover:bg-muted/40 hover:text-foreground transition-colors">
                                <span class="font-sans font-medium text-foreground">{{ $apiLink['label'] }}</span>
                                <span class="text-2xs text-muted-foreground">{{ $apiLink['route'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-metronic.card>
            @endif
        </div>
    </div>
</div>
@endsection
