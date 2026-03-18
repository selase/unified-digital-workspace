@props(['post'])

<article class="group flex flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100 transition hover:shadow-md">
    @if($post->featuredMedia)
        <div class="aspect-video overflow-hidden bg-gray-100">
            <img
                src="{{ Storage::disk($post->featuredMedia->disk)->url($post->featuredMedia->path) }}"
                alt="{{ $post->featuredMedia->alt_text ?? $post->title }}"
                class="h-full w-full object-cover transition group-hover:scale-105"
                loading="lazy"
            />
        </div>
    @endif

    <div class="flex flex-1 flex-col p-5">
        @if($post->categories->isNotEmpty())
            <div class="mb-2 flex flex-wrap gap-2">
                @foreach($post->categories->take(2) as $category)
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

        <h3 class="text-lg font-semibold text-gray-900 group-hover:text-gray-700">
            <a href="{{ $cmsUrl->route('posts.show', $post->slug) }}">
                {{ $post->title }}
            </a>
        </h3>

        @if($post->excerpt)
            <p class="mt-2 line-clamp-3 flex-1 text-sm text-gray-600">
                {{ $post->excerpt }}
            </p>
        @endif

        <div class="mt-4 flex items-center gap-3 text-xs text-gray-400">
            @if($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">
                    {{ $post->published_at->format('M d, Y') }}
                </time>
            @endif

            @if($post->author)
                <span>&middot;</span>
                <span>{{ $post->author->first_name }} {{ $post->author->last_name }}</span>
            @endif
        </div>
    </div>
</article>
