@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $title . ' — ' . $theme->siteName())

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="mb-8 text-3xl font-bold text-gray-900">{{ $title }}</h1>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($posts as $post)
                <article class="rounded-lg bg-gray-50 p-6">
                    <h2 class="text-xl font-semibold">
                        <a href="{{ $cmsUrl->route('newsletters.show', $post->slug) }}">{{ $post->title }}</a>
                    </h2>
                    @if($post->published_at)
                        <time datetime="{{ $post->published_at->toDateString() }}" class="mt-2 block text-sm text-gray-500">{{ $post->published_at->format('d/m/Y') }}</time>
                    @endif
                    @if($post->excerpt)
                        <p class="mt-4 text-gray-600">{{ $post->excerpt }}</p>
                    @endif
                </article>
            @empty
                <p class="text-gray-500">No newsletters published yet.</p>
            @endforelse
        </div>
        <div class="mt-10">{{ $posts->links() }}</div>
    </section>
@endsection
