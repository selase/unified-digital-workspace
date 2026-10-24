@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@php
    $slideIds = array_values(array_filter(array_map('intval', explode(',', (string) $theme->get('slider_media_ids')))));
    $slides = $slideIds === []
        ? collect()
        : \App\Modules\CmsCore\Models\Media::query()->whereIn('id', $slideIds)->get()->sortBy(fn ($media) => array_search($media->id, $slideIds))->values();
@endphp

@section('seo-title', $theme->siteName() . ' — ' . $theme->siteTagline())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $theme->siteName() . ' — ' . $theme->siteTagline(),
        'description' => $theme->siteTagline(),
        'canonical' => $cmsUrl->route('home'),
    ])
@endsection

@section('content')
    @if($slides->isNotEmpty())
        <section
            class="relative mx-auto mt-4 max-w-6xl overflow-hidden px-4"
            x-data="{ active: 0, total: {{ $slides->count() }}, timer: null, start() { this.timer = setInterval(() => this.active = (this.active + 1) % this.total, 6000) } }"
            x-init="start()"
            x-on:mouseenter="clearInterval(timer)"
            x-on:mouseleave="start()"
            aria-roledescription="carousel"
            aria-label="Featured"
        >
            <div class="relative aspect-[16/7] w-full overflow-hidden rounded-lg bg-gray-100">
                @foreach($slides as $index => $slide)
                    <img
                        src="{{ $slide->serveUrl(false) }}"
                        alt="{{ $slide->alt_text ?: $theme->siteName() }}"
                        class="absolute inset-0 h-full w-full object-cover transition-opacity duration-700"
                        x-bind:class="active === {{ $index }} ? 'opacity-100' : 'opacity-0'"
                        @if($index > 0) loading="lazy" @endif
                    />
                @endforeach
            </div>
            @if($slides->count() > 1)
                <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
                    @foreach($slides as $index => $slide)
                        <button
                            type="button"
                            class="h-2.5 w-2.5 rounded-full bg-white/70 ring-1 ring-black/20"
                            x-bind:class="active === {{ $index }} ? '!bg-white scale-125' : ''"
                            x-on:click="active = {{ $index }}"
                            aria-label="Show slide {{ $index + 1 }}"
                        ></button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($homepage?->body)
        <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="prose prose-lg mx-auto max-w-none text-center [&_img]:mx-auto [&_h1]:tracking-wide [&_h2]:tracking-wide">
                {!! $homepage->body !!}
            </div>
        </section>
    @endif
@endsection
