@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', $page->title . ' — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $page->title . ' — ' . $theme->siteName(),
        'description' => $page->excerpt ?? '',
        'canonical' => $cmsUrl->route('pages.show', $page->slug),
        'ogImage' => $page->featuredMedia
            ? $page->featuredMedia->url()
            : null,
    ])
@endsection

@section('content')
    {{-- Page hero header --}}
    <section style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white">{{ $page->title }}</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-white sm:text-4xl lg:text-5xl">{{ $page->title }}</h1>
            @if($page->excerpt)
                <p class="mt-4 max-w-2xl text-lg text-slate-300">{{ $page->excerpt }}</p>
            @endif
        </div>
    </section>

    {{-- Page body --}}
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                {{-- Featured image --}}
                @if($page->featuredMedia)
                    <div class="mb-10 overflow-hidden rounded-xl">
                        <img
                            src="{{ $page->featuredMedia->url() }}"
                            alt="{{ $page->featuredMedia->alt_text ?? $page->title }}"
                            class="w-full object-cover"
                        />
                    </div>
                @endif

                {{-- Rich content --}}
                <div class="prose prose-lg max-w-none" style="--tw-prose-links: var(--tgf-primary); --tw-prose-headings: var(--tgf-dark); --tw-prose-bullets: var(--tgf-primary); line-height: 1.8;">
                    <style>
                        .prose h2 { margin-top: 2.5rem; margin-bottom: 1rem; font-size: 1.5rem; font-weight: 700; padding-bottom: 0.5rem; border-bottom: 2px solid var(--tgf-primary-light); }
                        .prose h3 { margin-top: 1.75rem; margin-bottom: 0.75rem; font-size: 1.25rem; font-weight: 600; color: var(--tgf-primary-dark); }
                        .prose ul { padding-left: 1.5rem; }
                        .prose ul li { padding-left: 0.25rem; margin-bottom: 0.5rem; }
                        .prose ul li::marker { color: var(--tgf-primary); }
                        .prose p { margin-bottom: 1.25rem; }
                        .prose strong { color: var(--tgf-dark); }
                        .prose blockquote { border-left: 4px solid var(--tgf-primary); background: var(--tgf-light); padding: 1rem 1.5rem; border-radius: 0.375rem; }
                    </style>
                    {!! $page->body !!}
                </div>
            </div>
        </div>
    </section>

    {{-- Child pages --}}
    @if($page->children->isNotEmpty())
        <section class="border-t py-16" style="background: var(--tgf-light); border-color: var(--tgf-border);">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="mb-8 text-xl font-bold" style="color: var(--tgf-dark);">Related Pages</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($page->children as $child)
                        <a href="{{ $cmsUrl->route('pages.show', $child->slug) }}" class="group rounded-xl bg-white p-6 shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                            <h3 class="font-semibold transition group-hover:opacity-70" style="color: var(--tgf-dark);">{{ $child->title }}</h3>
                            @if($child->excerpt)
                                <p class="mt-2 text-sm" style="color: var(--tgf-muted);">{{ $child->excerpt }}</p>
                            @endif
                            <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium" style="color: var(--tgf-primary);">
                                Read more <span>&rarr;</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA banner at bottom of every page --}}
    <section class="py-14" style="background: var(--tgf-primary);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div>
                    <h3 class="text-xl font-bold text-white">Want to support thyroid health in Ghana?</h3>
                    <p class="mt-1 text-teal-100">Your contribution makes early detection and treatment possible.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent">Donate Now</a>
                    <a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="rounded-md border-2 border-white px-6 py-3 text-sm font-semibold text-white transition hover:bg-white hover:text-teal-700">Volunteer</a>
                </div>
            </div>
        </div>
    </section>
@endsection
