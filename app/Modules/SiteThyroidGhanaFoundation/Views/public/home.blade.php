@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', $theme->siteName() . ' — Thyroid Health Awareness in Ghana')

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $theme->siteName() . ' — Thyroid Health Awareness in Ghana',
        'description' => 'Creating awareness of thyroid diseases in Ghana, providing access to early detection, affordable treatment, and supporting thyroid research.',
        'canonical' => $cmsUrl->route('home'),
    ])
@endsection

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
        <div class="absolute inset-0 opacity-10">
            <svg class="h-full w-full" viewBox="0 0 1000 600" preserveAspectRatio="none"><circle cx="800" cy="100" r="400" fill="white" opacity="0.1"/><circle cx="200" cy="500" r="300" fill="white" opacity="0.05"/></svg>
        </div>
        <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8 lg:py-40">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-accent-light);">Thyroid Ghana Foundation</p>
                <h1 class="mt-4 text-4xl font-extrabold leading-tight text-white sm:text-5xl lg:text-6xl">
                    Advancing Thyroid Health Across Ghana
                </h1>
                <p class="mt-6 text-lg leading-relaxed text-slate-300">
                    Creating awareness, enabling early detection, and providing access to affordable treatment for thyroid diseases — because every Ghanaian deserves quality healthcare.
                </p>
                <div class="mt-10 flex flex-wrap gap-4">
                    <a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="tgf-btn-primary">Learn About Our Mission</a>
                    <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent">Support Our Cause</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Impact stats --}}
    <section class="relative -mt-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-px overflow-hidden rounded-xl bg-white shadow-xl border sm:grid-cols-2 lg:grid-cols-4" style="border-color: var(--tgf-border);">
                @php
                    $stats = [
                        ['value' => 'Since 2018', 'label' => 'Serving Communities', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['value' => '6+', 'label' => 'Regions Reached', 'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064'],
                        ['value' => '4', 'label' => 'Core Programs', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
                        ['value' => 'West Africa', 'label' => 'Regional Impact', 'icon' => 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9'],
                    ];
                @endphp
                @foreach($stats as $stat)
                    <div class="flex items-center gap-4 p-6 lg:p-8">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg" style="background: color-mix(in srgb, var(--tgf-primary) 10%, transparent);">
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
                <p class="mt-4 text-lg leading-relaxed" style="color: var(--tgf-muted);">
                    We provide comprehensive support across the full spectrum of thyroid disease management in Ghana.
                </p>
            </div>

            <div class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $services = [
                        ['title' => 'Surgery Access', 'desc' => 'Connecting patients with qualified surgical specialists throughout Ghana for thyroid procedures.', 'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'],
                        ['title' => 'Patient Forums', 'desc' => 'Operating support groups across multiple regions so patients never face thyroid disease alone.', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                        ['title' => 'Medicine Access', 'desc' => 'Facilitating affordable access to medications for all stages of thyroid conditions.', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                        ['title' => 'Research & Fundraising', 'desc' => 'Supporting thyroid research and collecting resources for cancer initiatives across West Africa.', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
                    ];
                @endphp
                @foreach($services as $service)
                    <div class="group rounded-xl bg-white p-8 shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-lg" style="background: color-mix(in srgb, var(--tgf-primary) 10%, transparent);">
                            <svg width="24" height="24" style="color: var(--tgf-primary); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $service['icon'] }}"/></svg>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--tgf-dark);">{{ $service['title'] }}</h3>
                        <p class="mt-3 text-sm leading-relaxed" style="color: var(--tgf-muted);">{{ $service['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Mission section --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-20">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Our Mission</p>
                    <h2 class="mt-3 text-3xl font-bold sm:text-4xl" style="color: var(--tgf-dark);">A Ghana where thyroid diseases are detected early and treated effectively</h2>
                    <p class="mt-6 text-base leading-relaxed" style="color: var(--tgf-muted);">
                        The Thyroid Ghana Foundation is a non-governmental organization dedicated to raising awareness about thyroid disorders and ensuring prompt, appropriate treatment access for affected Ghanaians. Since our founding in July 2018, we have worked tirelessly as a proud member of the Thyroid Federation International.
                    </p>
                    <p class="mt-4 text-base leading-relaxed" style="color: var(--tgf-muted);">
                        We create opportunities for early detection, support thyroid research and institutions involved in thyroid disease management, and advocate for improved healthcare practices for patients across the country.
                    </p>
                    <a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="tgf-btn-outline mt-8">Read Our Full Story</a>
                </div>
                <div class="rounded-2xl p-1" style="background: linear-gradient(135deg, var(--tgf-primary), var(--tgf-accent));">
                    <div class="rounded-xl bg-white p-10">
                        <blockquote>
                            <p class="text-lg font-medium italic leading-relaxed" style="color: var(--tgf-text);">
                                "We welcome you to the Thyroid Ghana Foundation and invite you to join us in our commitment to advancing thyroid health awareness, patient support, and research across Ghana and West Africa."
                            </p>
                            <footer class="mt-6 flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full" style="background: var(--tgf-primary); display: flex; align-items: center; justify-content: center;">
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

    {{-- Recent news --}}
    @if($recentPosts->isNotEmpty())
        <section class="py-20 lg:py-28" style="background: var(--tgf-light);">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Latest News</p>
                        <h2 class="mt-3 text-3xl font-bold" style="color: var(--tgf-dark);">Updates & announcements</h2>
                    </div>
                    <a href="{{ $cmsUrl->route('posts.archive') }}" class="hidden text-sm font-semibold transition hover:opacity-70 sm:inline-flex items-center gap-1" style="color: var(--tgf-primary);">
                        View all news <span>&rarr;</span>
                    </a>
                </div>

                <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($recentPosts->take(3) as $post)
                        <article class="group overflow-hidden rounded-xl bg-white shadow-sm border transition hover:shadow-lg" style="border-color: var(--tgf-border);">
                            @if($post->featuredMedia)
                                <div class="aspect-video overflow-hidden">
                                    <img src="{{ Storage::disk($post->featuredMedia->disk)->url($post->featuredMedia->path) }}" alt="{{ $post->featuredMedia->alt_text ?? $post->title }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy" />
                                </div>
                            @else
                                <div class="aspect-video" style="background: linear-gradient(135deg, var(--tgf-primary), #134E4A);"></div>
                            @endif
                            <div class="p-6">
                                @if($post->published_at)
                                    <time class="text-xs font-medium" style="color: var(--tgf-muted);" datetime="{{ $post->published_at->toDateString() }}">
                                        {{ $post->published_at->format('F j, Y') }}
                                    </time>
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
@endsection
