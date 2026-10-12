{{-- Custom template for the Media Gallery page --}}
@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Media Gallery — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Media Gallery — ' . $theme->siteName(),
        'description' => 'Watch videos, view photos, and explore media content from the Thyroid Ghana Foundation.',
        'canonical' => $cmsUrl->route('pages.show', 'media-gallery'),
    ])
@endsection

@section('content')
    {{-- Header --}}
    <section style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
            <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white">Media Gallery</span>
            </nav>
            <h1 class="text-3xl font-extrabold text-white sm:text-4xl lg:text-5xl">Media Gallery</h1>
            <p class="mt-4 max-w-2xl text-lg text-slate-300">Watch videos, explore campaign highlights, and stay informed about thyroid health in Ghana.</p>
        </div>
    </section>

    {{-- Video Section --}}
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Videos</p>
                <h2 class="mt-2 text-2xl font-bold" style="color: var(--tgf-dark);">Featured Videos</h2>
            </div>

            @php
                $videos = [
                    ['id' => 'qVNYLJCEn-A', 'title' => 'Official Launch of Thyroid Ghana Foundation', 'desc' => 'Coverage of the official launch event of the Thyroid Ghana Foundation, introducing our mission and vision.', 'category' => 'About Us'],
                    ['id' => 'Gn11u_1LAUE', 'title' => 'Feature Interview on TV3', 'desc' => 'Founder Dr. Nana Adwoa Konadu Dsane discusses thyroid health awareness on national television.', 'category' => 'Media'],
                    ['id' => 'D_iYMxXvp5I', 'title' => 'Include Thyroid Patients on NHIS', 'desc' => 'Advocacy for including thyroid disorder treatments under Ghana\'s National Health Insurance Scheme.', 'category' => 'Advocacy'],
                    ['id' => 'U3Y6-N-0ZsQ', 'title' => 'Happy Birthday to the Foundation', 'desc' => 'Celebrating the anniversary of the Thyroid Ghana Foundation and reflecting on our achievements.', 'category' => 'Events'],
                    ['id' => '98DMSeXAnX4', 'title' => 'A Safe and Healthy Christmas', 'desc' => 'Seasonal health tips and thyroid awareness message for the holiday season from the foundation.', 'category' => 'Awareness'],
                ];
            @endphp

            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($videos as $video)
                    <div class="group overflow-hidden rounded-xl bg-white shadow-sm transition hover:shadow-lg">
                        <div class="relative aspect-video bg-slate-900">
                            <img
                                src="https://img.youtube.com/vi/{{ $video['id'] }}/0.jpg"
                                alt="{{ $video['title'] }}"
                                class="h-full w-full object-cover opacity-80 transition group-hover:opacity-100"
                                loading="lazy"
                            />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <a
                                    href="https://www.youtube.com/watch?v={{ $video['id'] }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex h-16 w-16 items-center justify-center rounded-full bg-white/90 shadow-lg transition hover:scale-110 hover:bg-white"
                                    aria-label="Play {{ $video['title'] }}"
                                >
                                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24"><path fill="#DC2626" d="M8 5.14v13.72a1 1 0 001.5.87l11-6.86a1 1 0 000-1.74l-11-6.86A1 1 0 008 5.14z"/></svg>
                                </a>
                            </div>
                            <div class="absolute left-3 top-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium text-white" style="background: rgba(0,0,0,0.6);">{{ $video['category'] }}</span>
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold" style="color: var(--tgf-dark);">
                                <a href="https://www.youtube.com/watch?v={{ $video['id'] }}" target="_blank" rel="noopener" class="transition hover:opacity-70">{{ $video['title'] }}</a>
                            </h3>
                            <p class="mt-2 text-sm leading-relaxed" style="color: var(--tgf-muted);">{{ $video['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Photo gallery: International Thyroid Awareness Week 2026 --}}
    @php
        $galleryPhotos = \App\Modules\CmsCore\Models\Media::query()
            ->where('metadata->gallery', \App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026GallerySeeder::GALLERY)
            ->get()
            ->sortBy(fn ($photo) => (int) ($photo->metadata['order'] ?? 0))
            ->values();

        $galleryItems = $galleryPhotos->map(fn ($photo) => [
            'src' => $photo->url(),
            'alt' => (string) $photo->alt_text,
            'caption' => (string) ($photo->metadata['caption'] ?? $photo->title),
            'width' => $photo->width,
            'height' => $photo->height,
        ])->all();

        $storyUrl = $cmsUrl->route('posts.show', \App\Modules\SiteThyroidGhanaFoundation\Database\Seeders\ThyroidAwarenessWeek2026Seeder::STORY_SLUG);
    @endphp

    @if($galleryPhotos->isNotEmpty())
        <section id="awareness-week-2026" class="border-t py-16 lg:py-20" style="background: var(--tgf-light); border-color: var(--tgf-border);">
            <div
                class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
                x-data="{
                    items: @js($galleryItems),
                    open: false,
                    index: 0,
                    show(i) { this.index = i; this.open = true; document.body.classList.add('overflow-hidden'); },
                    close() { this.open = false; document.body.classList.remove('overflow-hidden'); },
                    next() { this.index = (this.index + 1) % this.items.length; },
                    prev() { this.index = (this.index - 1 + this.items.length) % this.items.length; },
                }"
                @keydown.escape.window="close()"
                @keydown.arrow-right.window="open && next()"
                @keydown.arrow-left.window="open && prev()"
            >
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Photos</p>
                        <h2 class="mt-2 text-2xl font-bold" style="color: var(--tgf-dark);">International Thyroid Awareness Week 2026</h2>
                        <p class="mt-2 max-w-2xl text-sm" style="color: var(--tgf-muted);">Highlights from the “Thyroid and Nutrition” forum at the University of Ghana Medical Centre, 26 May 2026.</p>
                    </div>
                    <a href="{{ $storyUrl }}" class="text-sm font-semibold transition hover:opacity-70" style="color: var(--tgf-primary);">Read the story &rarr;</a>
                </div>

                <div class="columns-2 gap-4 md:columns-3 lg:columns-4">
                    @foreach($galleryItems as $i => $item)
                        <button
                            type="button"
                            @click="show({{ $i }})"
                            class="group relative mb-4 block w-full overflow-hidden rounded-xl bg-slate-200 shadow-sm transition hover:shadow-lg focus:outline-none focus-visible:ring-2"
                            style="break-inside: avoid;"
                            aria-label="Open photo: {{ $item['caption'] }}"
                        >
                            <img
                                src="{{ $item['src'] }}"
                                alt="{{ $item['alt'] }}"
                                width="{{ $item['width'] }}"
                                height="{{ $item['height'] }}"
                                class="h-auto w-full transition duration-300 group-hover:scale-105"
                                loading="lazy"
                            />
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-3 text-left text-xs font-medium text-white opacity-0 transition group-hover:opacity-100">{{ $item['caption'] }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Lightbox. The outer layer carries no display utility so inline display:none holds before Alpine boots;
                     x-show.important is needed because utilities in this project's CSS are !important. --}}
                <div
                    x-show.important="open"
                    x-transition.opacity
                    style="display: none;"
                    class="fixed inset-0 z-50 bg-black/90"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Photo viewer"
                >
                    <div class="flex h-full w-full items-center justify-center p-4" @click.self="close()">
                        <button type="button" @click="close()" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/25" aria-label="Close viewer">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <button type="button" @click="prev()" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/25" aria-label="Previous photo">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="next()" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/25" aria-label="Next photo">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <figure class="flex max-h-full max-w-5xl flex-col items-center">
                            <img :src="items[index].src" :alt="items[index].alt" class="max-h-[80vh] w-auto max-w-full rounded-lg object-contain" />
                            <figcaption class="mt-3 text-center text-sm text-slate-200">
                                <span x-text="items[index].caption"></span>
                                <span class="ml-2 text-slate-400" x-text="(index + 1) + ' / ' + items.length"></span>
                            </figcaption>
                        </figure>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Follow CTA --}}
    <section class="py-14" style="background: var(--tgf-primary);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div>
                    <h3 class="text-xl font-bold text-white">Stay connected with our work</h3>
                    <p class="mt-1 text-teal-100">Follow us on social media for the latest updates, events, and educational content.</p>
                </div>
                <div class="flex gap-3">
                    <a href="https://www.facebook.com/thyroidghana" target="_blank" rel="noopener" class="rounded-md border-2 border-white px-6 py-3 text-sm font-semibold text-white transition hover:bg-white hover:text-teal-700">Follow on Facebook</a>
                </div>
            </div>
        </div>
    </section>
@endsection
