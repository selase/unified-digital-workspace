@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $post->title . ' — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $post->title . ' — ' . $theme->siteName(),
        'description' => $post->excerpt ?? '',
        'canonical' => $cmsUrl->route('posts.show', $post->slug),
        'type' => 'article',
        'ogImage' => $post->featuredMedia
            ? Storage::disk($post->featuredMedia->disk)->url($post->featuredMedia->path)
            : null,
    ])
@endsection

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('cms-core::components.breadcrumbs', [
            'items' => [
                ['label' => 'News', 'url' => $cmsUrl->route('posts.archive')],
                ['label' => $post->title],
            ],
        ])
    </div>

    <article class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl">
            {{-- Categories --}}
            @if($post->categories->isNotEmpty())
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach($post->categories as $category)
                        <a
                            href="{{ $cmsUrl->route('posts.category', $category->slug) }}"
                            class="text-xs font-medium uppercase tracking-wide"
                            style="color: var(--brand-color);"
                        >
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Title --}}
            <h1 class="text-3xl font-bold text-gray-900 sm:text-4xl">{{ $post->title }}</h1>

            {{-- Meta --}}
            <div class="mt-4 flex items-center gap-3 text-sm text-gray-500">
                @if($post->author)
                    <span>{{ $post->author->first_name }} {{ $post->author->last_name }}</span>
                @endif
                @if($post->published_at)
                    <span>&middot;</span>
                    <time datetime="{{ $post->published_at->toDateString() }}">
                        {{ $post->published_at->format('F j, Y') }}
                    </time>
                @endif
            </div>

            {{-- Featured image --}}
            @if($post->featuredMedia)
                <div class="mt-8">
                    <img
                        src="{{ Storage::disk($post->featuredMedia->disk)->url($post->featuredMedia->path) }}"
                        alt="{{ $post->featuredMedia->alt_text ?? $post->title }}"
                        class="w-full rounded-lg object-cover"
                    />
                </div>
            @endif

            {{-- Body --}}
            <div class="prose mt-8 max-w-none">
                {!! $post->body !!}
            </div>

            {{-- Tags --}}
            @if($post->tags->isNotEmpty())
                <div class="mt-10 border-t border-gray-100 pt-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-gray-700">Tags:</span>
                        @foreach($post->tags as $tag)
                            <a
                                href="{{ $cmsUrl->route('posts.tag', $tag->slug) }}"
                                class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-200"
                            >
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </article>

    {{-- Related posts --}}
    @if($relatedPosts->isNotEmpty())
        <section class="border-t border-gray-100 bg-gray-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="mb-6 text-xl font-semibold text-gray-900">Related Posts</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($relatedPosts as $relatedPost)
                        @include('cms-core::components.post-card', ['post' => $relatedPost])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
