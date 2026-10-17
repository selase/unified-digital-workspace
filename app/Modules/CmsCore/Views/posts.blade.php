@extends('layouts.metronic.app')

@section('title', 'Post Library')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Post Library"
        description="Publishing board for drafted, scheduled, and live content entries across your web portals."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">
                Back to Hub
            </a>
            @can('cms.categories.view')
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.categories.index') }}">
                    Categories
                </a>
            @endcan
            @can('cms.posts.create')
                <a class="kt-btn kt-btn-primary flex items-center gap-1.5" href="{{ route('cms-core.posts.create') }}">
                    <i class="ki-filled ki-plus text-base"></i>
                    New Post
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

    {{-- Post Table Card --}}
    <div class="kt-card kt-card-grid min-w-full">
        <div class="kt-card-header py-5 flex-wrap gap-3">
            <div class="flex items-center gap-2.5">
                <h3 class="kt-card-title text-base font-semibold">Publishing Queue</h3>
                <span class="kt-badge kt-badge-outline font-medium">{{ $posts->total() }} posts</span>
            </div>
            <form class="flex w-full lg:w-auto flex-wrap items-center gap-2.5 lg:justify-end" method="GET" action="{{ route('cms-core.posts.index') }}">
                <div class="relative w-full md:w-[280px]">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                        <i class="ki-filled ki-magnifier"></i>
                    </span>
                    <input
                        class="kt-input w-full pl-9"
                        name="search"
                        placeholder="Search title, slug, excerpt"
                        type="text"
                        value="{{ $search }}"
                    />
                </div>
                <div class="w-full sm:w-[160px]">
                    <select class="kt-select w-full" name="status">
                        <option value="">Any status</option>
                        <option value="draft" @selected($status === 'draft')>Draft</option>
                        <option value="published" @selected($status === 'published')>Published</option>
                        <option value="scheduled" @selected($status === 'scheduled')>Scheduled</option>
                        <option value="archived" @selected($status === 'archived')>Archived</option>
                    </select>
                </div>
                <div class="w-full sm:w-[200px]">
                    <select class="kt-select w-full" name="post_type_id">
                        <option value="">All post types</option>
                        @foreach($postTypes as $type)
                            <option value="{{ $type->id }}" @selected($postTypeId === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                @if($search !== '' || $status !== '' || $postTypeId > 0)
                    <a href="{{ route('cms-core.posts.index') }}" class="kt-btn kt-btn-link text-xs text-muted-foreground hover:text-foreground">Clear</a>
                @endif
            </form>
        </div>

        <div class="kt-card-content p-0">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border align-middle w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                            <th class="py-3 px-4 font-semibold text-start min-w-[300px]">Title & Slug</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[130px]">Type</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[200px]">Author</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[120px]">Status</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[150px]">Updated</th>
                            <th class="py-3 px-4 font-semibold text-end min-w-[180px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($posts as $post)
                            <tr class="hover:bg-muted/10 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="flex flex-col gap-0.5">
                                        <a class="font-medium text-foreground hover:text-primary transition-colors text-sm" href="{{ route('cms-core.posts.show', $post) }}">
                                            {{ $post->title }}
                                        </a>
                                        <span class="text-xs text-muted-foreground font-mono">{{ $post->slug }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="kt-badge kt-badge-outline text-xs">
                                        {{ $post->postType?->name ?: 'Post' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-bold text-2xs">
                                            {{ substr($post->author?->first_name ?: 'A', 0, 1) }}{{ substr($post->author?->last_name ?: '', 0, 1) }}
                                        </div>
                                        <span class="text-foreground text-xs font-medium">{{ $post->author?->displayName() ?: 'Unknown Author' }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @php
                                        $statusVariant = match ($post->status) {
                                            'published' => 'success',
                                            'scheduled' => 'warning',
                                            'archived' => 'danger',
                                            default => 'secondary',
                                        };
                                        $statusBadgeClass = match ($post->status) {
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
                                <td class="py-3.5 px-4 text-xs text-muted-foreground">
                                    {{ $post->updated_at?->diffForHumans() }}
                                </td>
                                <td class="py-3.5 px-4 text-end">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a class="kt-btn kt-btn-xs kt-btn-outline" href="{{ route('cms-core.posts.show', $post) }}">
                                            View
                                        </a>
                                        @can('cms.posts.update')
                                            <a class="kt-btn kt-btn-xs kt-btn-outline" href="{{ route('cms-core.posts.edit', $post) }}">
                                                Edit
                                            </a>
                                        @endcan
                                        @can('cms.posts.delete')
                                            <form action="{{ route('cms-core.posts.destroy', $post) }}" method="POST" class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" onclick="return confirm('Delete this post?');" type="submit">
                                                    Delete
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-12 text-center text-muted-foreground" colspan="6">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="h-8 w-8 text-muted-foreground/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                                        </svg>
                                        <span>No posts found for this filter.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($posts->hasPages())
                <div class="p-4 border-t border-border">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
