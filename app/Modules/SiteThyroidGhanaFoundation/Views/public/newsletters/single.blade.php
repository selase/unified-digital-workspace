@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', $post->title . ' — Newsletters — ' . $theme->siteName())

@php
    $pdf = $post->media->firstWhere('mime_type', 'application/pdf');
@endphp

@section('content')
    {{-- Page header --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <a href="{{ $cmsUrl->route('newsletters.archive') }}" class="transition hover:text-white">Newsletters</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white line-clamp-1">{{ $post->title }}</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-white sm:text-4xl">{{ $post->title }}</h1>
            @if($post->published_at)
                <p class="mt-4 text-sm text-slate-400">Published {{ $post->published_at->format('F j, Y') }}</p>
            @endif
        </div>
    </section>

    <section class="py-12 lg:py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if($post->excerpt)
                <p class="mb-8 max-w-3xl text-lg" style="color: var(--tgf-muted);">{{ $post->excerpt }}</p>
            @endif

            @if($pdf)
                {{-- PDF actions bar --}}
                <div class="mb-4 flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3" style="border: 1px solid var(--tgf-border);">
                    <div class="flex items-center gap-2 text-sm font-medium" style="color: var(--tgf-text);">
                        <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM6 20V4h5v7h7v9H6z"/></svg>
                        {{ $pdf->original_filename ?? $pdf->filename . '.' . $pdf->extension }}
                    </div>
                    <a href="{{ $pdf->url() }}" target="_blank" rel="noopener" class="tgf-btn-primary text-xs" style="padding: 0.5rem 1.25rem;">
                        <span class="flex items-center gap-1.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Download PDF
                        </span>
                    </a>
                </div>

                {{-- Embedded PDF viewer --}}
                <div class="overflow-hidden rounded-lg shadow-sm" style="border: 1px solid var(--tgf-border);">
                    <iframe src="{{ $pdf->url() }}#toolbar=1&navpanes=0" class="h-[80vh] w-full" title="{{ $post->title }}"></iframe>
                </div>
            @endif

            @if($post->body)
                <div class="prose prose-lg mt-10 max-w-3xl" style="color: var(--tgf-text);">
                    {!! $post->body !!}
                </div>
            @endif

            {{-- Back link --}}
            <div class="mt-12">
                <a href="{{ $cmsUrl->route('newsletters.archive') }}" class="inline-flex items-center gap-2 text-sm font-medium transition hover:opacity-70" style="color: var(--tgf-primary);">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to Newsletters
                </a>
            </div>
        </div>
    </section>
@endsection
