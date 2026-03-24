@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Newsletters — ' . $theme->siteName())

@section('content')
    {{-- Page header --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white">Newsletters</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-white sm:text-4xl">Newsletters</h1>
            <p class="mt-4 max-w-2xl text-lg text-slate-300">Browse our publications and stay up to date with the latest from the Thyroid Ghana Foundation.</p>
        </div>
    </section>

    {{-- Newsletter grid --}}
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if($posts->isNotEmpty())
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($posts as $post)
                        @php
                            $pdf = $post->media->firstWhere('mime_type', 'application/pdf');
                        @endphp
                        <a href="{{ $cmsUrl->route('newsletters.show', $post->slug) }}" class="group overflow-hidden rounded-xl bg-white shadow-sm transition hover:shadow-lg">
                            @if($post->featuredMedia)
                                <div class="aspect-[3/4] overflow-hidden">
                                    <img src="{{ $post->featuredMedia->url() }}" alt="{{ $post->title }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                                </div>
                            @else
                                <div class="flex aspect-[3/4] items-center justify-center" style="background: linear-gradient(135deg, var(--tgf-primary), #134E4A);">
                                    <svg class="h-16 w-16 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                                </div>
                            @endif
                            <div class="p-6">
                                @if($post->published_at)
                                    <time class="text-xs font-medium" style="color: var(--tgf-muted);" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                                @endif
                                <h3 class="mt-2 text-lg font-semibold leading-snug" style="color: var(--tgf-dark);">{{ $post->title }}</h3>
                                @if($post->excerpt)
                                    <p class="mt-2 line-clamp-2 text-sm" style="color: var(--tgf-muted);">{{ $post->excerpt }}</p>
                                @endif
                                @if($pdf)
                                    <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium" style="color: var(--tgf-primary);">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        PDF Available
                                    </span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $posts->links() }}
                </div>
            @else
                <div class="py-20 text-center">
                    <svg class="mx-auto h-16 w-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    <p class="mt-4 text-lg" style="color: var(--tgf-muted);">No newsletters published yet.</p>
                </div>
            @endif
        </div>
    </section>
@endsection
