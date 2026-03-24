@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', ($title ?? 'News') . ' — ' . $theme->siteName())

@section('content')
    {{-- Page header --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white">{{ $title ?? 'News' }}</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-white sm:text-4xl">{{ $title ?? 'News & Updates' }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-slate-300">Stay informed about our latest activities, campaigns, and the impact we are making across Ghana.</p>
        </div>
    </section>

    {{-- Articles grid --}}
    <section class="py-10 lg:py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {{-- Search --}}
            <div class="mb-6 flex justify-end">
                <form action="{{ $cmsUrl->route('posts.archive') }}" method="GET" class="flex items-center gap-2">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search..." class="w-48 rounded-md border bg-white py-1.5 pl-10 pr-3 text-sm outline-none transition focus:ring-1 focus:w-64" style="border-color: var(--tgf-border); --tw-ring-color: var(--tgf-primary);" />
                    </div>
                    @if(!empty($search))
                        <a href="{{ $cmsUrl->route('posts.archive') }}" class="text-xs font-medium transition hover:opacity-70" style="color: var(--tgf-primary);">Clear</a>
                    @endif
                </form>
            </div>

            @if($posts->isNotEmpty())
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($posts as $post)
                        <article class="group overflow-hidden rounded-xl bg-white shadow-sm transition hover:shadow-lg">
                            @if($post->featuredMedia)
                                <div class="aspect-video overflow-hidden">
                                    <img src="{{ $post->featuredMedia->url() }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                                </div>
                            @else
                                <div class="aspect-video" style="background: linear-gradient(135deg, var(--tgf-primary), #134E4A);"></div>
                            @endif
                            <div class="p-6">
                                @if($post->categories->isNotEmpty())
                                    <div class="mb-2 flex flex-wrap gap-2">
                                        @foreach($post->categories->take(2) as $category)
                                            <a href="{{ $cmsUrl->route('posts.category', $category->slug) }}" class="text-xs font-medium uppercase tracking-wide" style="color: var(--tgf-primary);">{{ $category->name }}</a>
                                        @endforeach
                                    </div>
                                @endif
                                @if($post->published_at)
                                    <time class="text-xs font-medium" style="color: var(--tgf-muted);" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                                @endif
                                <h3 class="mt-2 text-lg font-semibold leading-snug" style="color: var(--tgf-dark);">
                                    <a href="{{ $cmsUrl->route('posts.show', $post->slug) }}" class="transition hover:opacity-70">{{ $post->title }}</a>
                                </h3>
                                @if($post->excerpt)
                                    <p class="mt-2 line-clamp-2 text-sm" style="color: var(--tgf-muted);">{{ $post->excerpt }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $posts->links() }}
                </div>
            @else
                <div class="py-20 text-center">
                    <p class="text-lg" style="color: var(--tgf-muted);">
                        @if(!empty($search))
                            No articles matching "{{ $search }}".
                        @else
                            No articles found.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
