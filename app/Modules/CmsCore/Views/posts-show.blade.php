@extends('layouts.metronic.app')

@section('title', 'Post Detail')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $post->title }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Slug: {{ $post->slug }} · Status: {{ ucfirst($post->status) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @can('cms.posts.update')
                        <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.edit', $post) }}">Edit</a>
                    @endcan
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">Back to Library</a>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="kt-card p-6 xl:col-span-8">
                <h2 class="text-lg font-semibold text-foreground">Content</h2>
                @if($post->excerpt)
                    <p class="mt-4 text-sm text-muted-foreground">{{ $post->excerpt }}</p>
                @endif
                <div class="prose mt-4 max-w-none text-sm text-foreground">{!! $post->body !!}</div>
            </div>

            <div class="kt-card p-6 xl:col-span-4">
                <h2 class="text-lg font-semibold text-foreground">Publishing Metadata</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Post Type</p>
                        <p class="mt-1 text-sm text-foreground">{{ $post->postType?->name ?: 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Author</p>
                        <p class="mt-1 text-sm text-foreground">{{ $post->author?->displayName() ?: 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Editor</p>
                        <p class="mt-1 text-sm text-foreground">{{ $post->editor?->displayName() ?: 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Published At</p>
                        <p class="mt-1 text-sm text-foreground">{{ $post->published_at?->format('M d, Y H:i') ?: 'Not published' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-muted-foreground">Scheduled For</p>
                        <p class="mt-1 text-sm text-foreground">{{ $post->scheduled_for?->format('M d, Y H:i') ?: 'Not scheduled' }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-xs uppercase text-muted-foreground">Featured Media</p>
                    <div class="mt-2">
                        @if($post->featuredMedia)
                            <span class="kt-badge kt-badge-outline">
                                {{ $post->featuredMedia->title ?: $post->featuredMedia->original_filename ?: $post->featuredMedia->filename }}
                            </span>
                        @else
                            <span class="text-sm text-muted-foreground">No featured media selected.</span>
                        @endif
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-xs uppercase text-muted-foreground">Categories</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse($post->categories as $category)
                            <span class="kt-badge kt-badge-outline">{{ $category->name }}</span>
                        @empty
                            <span class="text-sm text-muted-foreground">No categories assigned.</span>
                        @endforelse
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-xs uppercase text-muted-foreground">Tags</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse($post->tags as $tag)
                            <span class="kt-badge kt-badge-outline">{{ $tag->name }}</span>
                        @empty
                            <span class="text-sm text-muted-foreground">No tags assigned.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
