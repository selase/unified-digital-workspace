@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', $post->title . ' — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $post->title . ' — ' . $theme->siteName(),
        'description' => $post->excerpt ?? '',
        'canonical' => $cmsUrl->route('posts.show', $post->slug),
        'type' => 'article',
        'ogImage' => $post->featuredMedia
            ? $post->featuredMedia->url()
            : null,
    ])
@endsection

@section('content')
    {{-- Article header --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ $cmsUrl->route('posts.archive') }}" class="transition hover:text-white">News</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white line-clamp-1">{{ $post->title }}</span>
            </nav>

            @if($post->categories->isNotEmpty())
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach($post->categories as $category)
                        <a href="{{ $cmsUrl->route('posts.category', $category->slug) }}" class="rounded-full px-3 py-1 text-xs font-medium" style="background: rgba(255,255,255,0.15); color: var(--tgf-accent-light);">{{ $category->name }}</a>
                    @endforeach
                </div>
            @endif

            <h1 class="max-w-3xl text-3xl font-extrabold text-white sm:text-4xl">{{ $post->title }}</h1>

            <div class="mt-4 flex items-center gap-4 text-sm text-slate-400">
                @if($post->author)
                    <span>{{ $post->author->first_name }} {{ $post->author->last_name }}</span>
                @endif
                @if($post->published_at)
                    <span>&middot;</span>
                    <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                @endif
            </div>
        </div>
    </section>

    {{-- Article body --}}
    <article class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl">
                {{-- Embedded media (video/audio) --}}
                @include('cms-core::components.post-media', ['post' => $post])

                {{-- Featured image (only if no video) --}}
                @if($post->featuredMedia && !$post->meta?->where('key', 'video_url')->first()?->value)
                    <div class="mb-10 overflow-hidden rounded-xl">
                        <img src="{{ $post->featuredMedia->url() }}" alt="{{ $post->featuredMedia->alt_text ?? $post->title }}" class="w-full object-cover" />
                    </div>
                @endif

                <div class="prose prose-lg max-w-none prose-headings:font-bold prose-a:font-semibold prose-a:no-underline" style="--tw-prose-links: var(--tgf-primary); --tw-prose-headings: var(--tgf-dark);">
                    {!! $post->body !!}
                </div>

                @if($post->tags->isNotEmpty())
                    <div class="mt-10 border-t pt-6" style="border-color: var(--tgf-border);">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium" style="color: var(--tgf-muted);">Tags:</span>
                            @foreach($post->tags as $tag)
                                <a href="{{ $cmsUrl->route('posts.tag', $tag->slug) }}" class="rounded-full px-3 py-1 text-xs font-medium transition hover:opacity-70" style="background: var(--tgf-light); color: var(--tgf-text);">{{ $tag->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </article>

    {{-- Related posts --}}
    @if($relatedPosts->isNotEmpty())
        <section class="border-t py-16" style="background: var(--tgf-light); border-color: var(--tgf-border);">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="mb-8 text-xl font-bold" style="color: var(--tgf-dark);">Related News</h2>
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($relatedPosts as $relatedPost)
                        <article class="group overflow-hidden rounded-xl bg-white shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                            @if($relatedPost->featuredMedia)
                                <div class="aspect-video overflow-hidden">
                                    <img src="{{ $relatedPost->featuredMedia->url() }}" alt="{{ $relatedPost->title }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                                </div>
                            @else
                                <div class="aspect-video" style="background: linear-gradient(135deg, var(--tgf-primary), #134E4A);"></div>
                            @endif
                            <div class="p-5">
                                <h3 class="font-semibold" style="color: var(--tgf-dark);">
                                    <a href="{{ $cmsUrl->route('posts.show', $relatedPost->slug) }}" class="transition hover:opacity-70">{{ $relatedPost->title }}</a>
                                </h3>
                                @if($relatedPost->published_at)
                                    <time class="mt-1 block text-xs" style="color: var(--tgf-muted);">{{ $relatedPost->published_at->format('F j, Y') }}</time>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
