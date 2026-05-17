@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', $theme->siteName() . ' — Advancing Thyroid Health Across Ghana')

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $theme->siteName() . ' — Advancing Thyroid Health Across Ghana',
        'description' => 'Creating awareness of thyroid diseases in Ghana, providing access to early detection, affordable treatment, and supporting thyroid research.',
        'canonical' => $cmsUrl->route('home'),
    ])
@endsection

@section('content')
    {{-- Hero Carousel --}}
    <section
        x-data="carousel()"
        x-init="start()"
        @mouseenter="pause()"
        @mouseleave="resume()"
        class="relative overflow-hidden"
        style="background: var(--tgf-dark);"
    >
        <div class="relative" style="min-height: 520px;">
            @php
                // Load slider posts — managed via CMS as PostType "slider"
                $sliderPosts = \App\Modules\CmsCore\Models\Post::query()
                    ->published()
                    ->forType('slider')
                    ->with(['featuredMedia', 'meta'])
                    ->orderBy('sort_order')
                    ->get();

                $gradients = [
                    'linear-gradient(135deg, #0F172A 0%, #134E4A 100%)',
                    'linear-gradient(135deg, #134E4A 0%, #0F766E 100%)',
                    'linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%)',
                    'linear-gradient(135deg, #0F766E 0%, #134E4A 100%)',
                ];

                // Helper to get meta value (handles array cast)
                $getMeta = function ($post, $key) {
                    $val = $post->meta?->where('key', $key)->first()?->value;
                    return is_array($val) ? ($val[0] ?? '') : ($val ?? '');
                };
            @endphp

            @foreach($sliderPosts as $i => $slide)
                @php
                    $bgImage = $slide->featuredMedia ? $slide->featuredMedia->url() : null;
                    $videoUrl = $getMeta($slide, 'video_url');

                    // Identify the video source type so we render the right element.
                    //   - file:    a direct .mp4/.webm/.ogg URL (media library upload)
                    //   - youtube: a youtu.be / youtube.com URL → background iframe
                    //   - vimeo:   a vimeo.com URL → background iframe with background=1
                    $videoKind = null;
                    $videoEmbedSrc = null;

                    if ($videoUrl) {
                        if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $videoUrl)) {
                            $videoKind = 'file';
                        } elseif (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})#', $videoUrl, $m)) {
                            $videoKind = 'youtube';
                            // playlist=ID is required for loop=1 on a single video.
                            $videoEmbedSrc = 'https://www.youtube.com/embed/'.$m[1]
                                .'?autoplay=1&mute=1&loop=1&playlist='.$m[1]
                                .'&controls=0&showinfo=0&modestbranding=1&rel=0&iv_load_policy=3&playsinline=1';
                        } elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $videoUrl, $m)) {
                            $videoKind = 'vimeo';
                            // background=1 hides controls and triggers autoplay+loop+mute.
                            $videoEmbedSrc = 'https://player.vimeo.com/video/'.$m[1].'?background=1&autoplay=1&loop=1&muted=1';
                        }
                    }

                    $hasVideo = $videoKind !== null;

                    if ($hasVideo) {
                        // Video paints itself; the wrapper just needs the dark base
                        // (visible while the video buffers or if it fails to load).
                        $bgStyle = 'background: var(--tgf-dark);';
                    } elseif ($bgImage) {
                        $bgStyle = "background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('{$bgImage}') center/cover no-repeat;";
                    } else {
                        $bgStyle = 'background: '.$gradients[$i % count($gradients)].';';
                    }
                @endphp
                <div
                    x-show="current === {{ $i }}"
                    x-cloak
                    x-transition:enter="transition-opacity duration-300 ease-out"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity duration-300 ease-in"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 flex items-center"
                    style="{{ $bgStyle }}"
                >
                    @if($videoKind === 'file')
                        <video
                            class="absolute inset-0 h-full w-full object-cover"
                            autoplay muted loop playsinline preload="metadata"
                            @if($bgImage) poster="{{ $bgImage }}" @endif
                        >
                            <source src="{{ $videoUrl }}" />
                        </video>
                        <div class="absolute inset-0" style="background: rgba(15,23,42,0.55);"></div>
                    @elseif($hasVideo)
                        {{-- YouTube/Vimeo background embed. The wrapper hides overflow and the
                             iframe is over-sized + centered so 16:9 video crops to fill the slide
                             without letterboxing on either dimension. --}}
                        <div class="pointer-events-none absolute inset-0 overflow-hidden">
                            <iframe
                                src="{{ $videoEmbedSrc }}"
                                title="Slide background video"
                                allow="autoplay; encrypted-media; picture-in-picture"
                                allowfullscreen
                                frameborder="0"
                                class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2"
                                style="width: 100vw; height: 56.25vw; min-height: 100%; min-width: 177.78vh;"
                            ></iframe>
                        </div>
                        <div class="absolute inset-0" style="background: rgba(15,23,42,0.55);"></div>
                    @endif
                    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div class="max-w-2xl">
                            @if($slide->body)
                                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-accent-light);">{{ strip_tags($slide->body) }}</p>
                            @endif
                            <h2 class="mt-4 text-3xl font-extrabold leading-tight text-white sm:text-4xl lg:text-5xl">{{ $slide->title }}</h2>
                            @if($slide->excerpt)
                                <p class="mt-5 text-base leading-relaxed text-slate-300 sm:text-lg">{{ $slide->excerpt }}</p>
                            @endif
                            <div class="mt-8 flex flex-wrap gap-4">
                                @if($getMeta($slide, 'cta_label'))
                                    <a href="{{ $getMeta($slide, 'cta_url') ?: '#' }}" class="tgf-btn-primary">{{ $getMeta($slide, 'cta_label') }}</a>
                                @endif
                                @if($getMeta($slide, 'cta2_label'))
                                    <a href="{{ $getMeta($slide, 'cta2_url') ?: '#' }}" class="tgf-btn-accent">{{ $getMeta($slide, 'cta2_label') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Dots --}}
            <div class="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-2">
                @foreach($sliderPosts as $i => $s)
                    <button
                        @click="goTo({{ $i }})"
                        class="h-2 rounded-full transition-all duration-300"
                        :class="current === {{ $i }} ? 'w-8 bg-white' : 'w-2 bg-white/40'"
                        aria-label="Slide {{ $i + 1 }}"
                    ></button>
                @endforeach
            </div>

            {{-- Arrows --}}
            <button @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white/70 transition hover:bg-white/20 hover:text-white" aria-label="Previous">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white/70 transition hover:bg-white/20 hover:text-white" aria-label="Next">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </section>

    {{-- Impact stats --}}
    <section class="relative -mt-8 z-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-px overflow-hidden rounded-xl bg-white shadow-xl border sm:grid-cols-2 lg:grid-cols-4" style="border-color: var(--tgf-border);">
                @php
                    $stats = [
                        ['value' => 'Since 2018', 'label' => 'Serving Communities', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['value' => '1,300+', 'label' => 'Cases at KBTH (2004-2010)', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                        ['value' => '87.8%', 'label' => 'Female Patients', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                        ['value' => 'West Africa', 'label' => 'Regional Impact', 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9'],
                    ];
                @endphp
                @foreach($stats as $stat)
                    <div class="flex items-center gap-4 bg-white p-6 lg:p-8">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg" style="background: var(--tgf-primary-light);">
                            <svg width="24" height="24" style="color: var(--tgf-primary); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $stat['icon'] }}"/></svg>
                        </div>
                        <div>
                            <p class="text-xl font-bold" style="color: var(--tgf-dark);">{{ $stat['value'] }}</p>
                            <p class="text-sm" style="color: var(--tgf-muted);">{{ $stat['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- What We Do --}}
    <section class="py-20 lg:py-28" style="background: var(--tgf-light);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">What We Do</p>
                <h2 class="mt-3 text-3xl font-bold sm:text-4xl" style="color: var(--tgf-dark);">Four pillars of thyroid health support</h2>
            </div>

            <div class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $services = [
                        ['title' => 'Surgery Access', 'desc' => 'Connecting patients with qualified surgical specialists throughout Ghana for thyroid procedures at subsidized costs.', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
                        ['title' => 'Patient Forums', 'desc' => 'Operating support groups across multiple regions so patients never face thyroid disease alone. Now in Northern Ghana.', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                        ['title' => 'Medicine Access', 'desc' => 'Facilitating affordable access to medications for all stages of thyroid conditions, including free distribution to needy patients.', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                        ['title' => 'Research & Fundraising', 'desc' => 'Supporting thyroid research, establishing data collection systems, and fundraising for cancer initiatives across West Africa.', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
                    ];
                @endphp
                @foreach($services as $service)
                    <div class="group rounded-xl bg-white p-8 shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-lg" style="background: var(--tgf-primary-light);">
                            <svg width="24" height="24" style="color: var(--tgf-primary); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $service['icon'] }}"/></svg>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--tgf-dark);">{{ $service['title'] }}</h3>
                        <p class="mt-3 text-sm leading-relaxed" style="color: var(--tgf-muted);">{{ $service['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Mission + Founder Quote --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Our Mission</p>
                    <h2 class="mt-3 text-3xl font-bold sm:text-4xl" style="color: var(--tgf-dark);">A Ghana where thyroid diseases are detected early and treated effectively</h2>
                    <p class="mt-6 text-base leading-relaxed" style="color: var(--tgf-muted);">
                        Founded in July 2018 by Mrs. Nana Adwoa Konadu Dsane following her own battle with hyperthyroidism, the Thyroid Ghana Foundation is a proud member of the Thyroid Federation International working to transform thyroid healthcare in Ghana.
                    </p>
                    <p class="mt-4 text-base leading-relaxed" style="color: var(--tgf-muted);">
                        We create opportunities for early detection, support research and institutions involved in thyroid disease management, and advocate for improved healthcare practices for patients across the country.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="tgf-btn-outline">Read Our Full Story</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'the-founder') }}" class="tgf-btn-primary">Meet the Founder</a>
                    </div>
                </div>
                <div class="rounded-2xl p-1" style="background: linear-gradient(135deg, var(--tgf-primary), var(--tgf-accent));">
                    <div class="rounded-xl bg-white p-8 sm:p-10">
                        <blockquote>
                            <svg width="32" height="32" class="mb-4 opacity-20" style="color: var(--tgf-primary);" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10H14.017zM0 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151C7.546 6.068 5.983 8.789 5.983 11H10v10H0z"/></svg>
                            <p class="text-lg font-medium italic leading-relaxed" style="color: var(--tgf-text);">
                                "We welcome you to the Thyroid Ghana Foundation and invite you to join us in our commitment to advancing thyroid health awareness, patient support, and research across Ghana and West Africa."
                            </p>
                            <footer class="mt-6 flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" style="background: var(--tgf-primary);">
                                    <span class="text-sm font-bold text-white">NK</span>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--tgf-dark);">Nana Adwoa Konadu Dsane</p>
                                    <p class="text-xs" style="color: var(--tgf-muted);">Founder & President</p>
                                </div>
                            </footer>
                        </blockquote>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Latest News --}}
    @if($recentPosts->isNotEmpty())
        <section class="py-20 lg:py-28" style="background: var(--tgf-light);">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Latest News</p>
                        <h2 class="mt-3 text-3xl font-bold" style="color: var(--tgf-dark);">Updates & announcements</h2>
                    </div>
                    <a href="{{ $cmsUrl->route('posts.archive') }}" class="hidden items-center gap-1 text-sm font-semibold transition hover:opacity-70 sm:inline-flex" style="color: var(--tgf-primary);">
                        View all news <span>&rarr;</span>
                    </a>
                </div>

                <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($recentPosts->take(3) as $post)
                        <article class="group overflow-hidden rounded-xl bg-white shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                            @if($post->featuredMedia)
                                <div class="aspect-video overflow-hidden">
                                    <img src="{{ $post->featuredMedia->url() }}" alt="{{ $post->featuredMedia->alt_text ?? $post->title }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                                </div>
                            @else
                                <div class="aspect-video" style="background: linear-gradient(135deg, var(--tgf-primary), #134E4A);"></div>
                            @endif
                            <div class="p-6">
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

                <div class="mt-8 text-center sm:hidden">
                    <a href="{{ $cmsUrl->route('posts.archive') }}" class="tgf-btn-outline">View All News</a>
                </div>
            </div>
        </section>
    @endif

    {{-- Media / YouTube Section --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Media Gallery</p>
                <h2 class="mt-3 text-3xl font-bold" style="color: var(--tgf-dark);">Watch, learn, and stay informed</h2>
                <p class="mx-auto mt-4 max-w-2xl text-base" style="color: var(--tgf-muted);">
                    Follow our work through video content featuring awareness campaigns, patient stories, and educational resources about thyroid health.
                </p>
            </div>

            <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $videos = [
                        ['id' => 'qVNYLJCEn-A', 'title' => 'Official Launch of TGF', 'desc' => 'Coverage of the official launch event of the Thyroid Ghana Foundation.'],
                        ['id' => 'Gn11u_1LAUE', 'title' => 'Feature Interview on TV3', 'desc' => 'Founder discusses thyroid health awareness on national television.'],
                        ['id' => 'D_iYMxXvp5I', 'title' => 'Thyroid Patients & NHIS', 'desc' => 'Advocating for thyroid treatment under the National Health Insurance Scheme.'],
                    ];
                @endphp
                @foreach($videos as $video)
                    <div class="group overflow-hidden rounded-xl bg-white shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                        <div class="relative aspect-video bg-slate-900">
                            <img
                                src="https://img.youtube.com/vi/{{ $video['id'] }}/0.jpg"
                                alt="{{ $video['title'] }}"
                                class="h-full w-full object-cover opacity-80 transition group-hover:opacity-100"
                                loading="lazy"
                            />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <a href="https://www.youtube.com/watch?v={{ $video['id'] }}" target="_blank" rel="noopener" class="flex h-16 w-16 items-center justify-center rounded-full bg-white/90 shadow-lg transition hover:scale-110 hover:bg-white">
                                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24"><path fill="#DC2626" d="M8 5.14v13.72a1 1 0 001.5.87l11-6.86a1 1 0 000-1.74l-11-6.86A1 1 0 008 5.14z"/></svg>
                                </a>
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold" style="color: var(--tgf-dark);">{{ $video['title'] }}</h3>
                            <p class="mt-1 text-sm" style="color: var(--tgf-muted);">{{ $video['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <a href="{{ $cmsUrl->route('pages.show', 'media-gallery') }}" class="tgf-btn-outline">View Full Gallery</a>
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="py-20 lg:py-28" style="background: var(--tgf-primary);">
        <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Help us make a difference</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-teal-100">
                Your support enables us to reach more communities, fund critical research, and provide life-changing treatment to thyroid patients across Ghana.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent">Make a Donation</a>
                <a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="rounded-md border-2 border-white px-8 py-3 text-sm font-semibold text-white transition hover:bg-white hover:text-teal-700">Become a Volunteer</a>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
        function carousel() {
            // Hero rotation cadence. 10s gives video slides time to actually
            // play and reading slides time to be read. Hover pauses the timer
            // so a visitor reading the headline doesn't get yanked away.
            const INTERVAL_MS = 10000;

            return {
                current: 0,
                total: {{ $sliderPosts->count() }},
                timer: null,
                startedAt: null,
                start() {
                    // Guard against a double-start (defensive — Alpine init
                    // should fire only once, but if it ever doesn't, the
                    // existing interval is cleared before scheduling a new one).
                    if (this.timer) { clearInterval(this.timer); this.timer = null; }

                    if (this.total <= 1) {
                        console.info('[hero] auto-advance disabled: only ' + this.total + ' slide(s).');
                        return;
                    }

                    this.startedAt = Date.now();
                    this.timer = setInterval(() => this.next(), INTERVAL_MS);
                    console.info('[hero] auto-advance ON (' + this.total + ' slides, every ' + (INTERVAL_MS / 1000) + 's)');
                },
                next() { this.current = (this.current + 1) % this.total; },
                prev() { this.current = (this.current - 1 + this.total) % this.total; },
                goTo(i) {
                    this.current = i;
                    this.start();
                },
                pause() {
                    if (this.timer) { clearInterval(this.timer); this.timer = null; }
                },
                resume() {
                    if (!this.timer && this.total > 1) {
                        this.timer = setInterval(() => this.next(), INTERVAL_MS);
                    }
                },
            };
        }
    </script>
    @endpush
@endsection
