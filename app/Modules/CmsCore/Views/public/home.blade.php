@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $theme->siteName() . ' — ' . $theme->siteTagline())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $theme->siteName() . ' — ' . $theme->siteTagline(),
        'description' => $theme->siteTagline(),
        'canonical' => $cmsUrl->route('home'),
    ])
@endsection

@section('content')
    {{-- Hero section --}}
    @include('cms-core::components.hero', [
        'title' => $homepage?->title ?? $theme->siteName(),
        'subtitle' => $homepage?->excerpt ?? $theme->siteTagline(),
        'image' => $homepage?->featuredMedia
            ? Storage::disk($homepage->featuredMedia->disk)->url($homepage->featuredMedia->path)
            : null,
        'ctaText' => 'Read Latest News',
        'ctaUrl' => $cmsUrl->route('posts.archive'),
    ])

    {{-- Homepage body content --}}
    @if($homepage?->body)
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="prose max-w-none">
                {!! $homepage->body !!}
            </div>
        </section>
    @endif

    {{-- Recent posts --}}
    @if($recentPosts->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-8 flex items-center justify-between">
                <h2 class="text-2xl font-semibold text-gray-900">Latest News</h2>
                <a
                    href="{{ $cmsUrl->route('posts.archive') }}"
                    class="text-sm font-medium transition hover:underline"
                    style="color: var(--brand-color);"
                >
                    View all &rarr;
                </a>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($recentPosts as $post)
                    @include('cms-core::components.post-card', ['post' => $post])
                @endforeach
            </div>
        </section>
    @endif
@endsection
