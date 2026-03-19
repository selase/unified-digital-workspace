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
                    ['id' => 'Gn11u_1LAUE', 'title' => 'Feature Interview on TV3', 'desc' => 'Founder Nana Adwoa Konadu Dsane discusses thyroid health awareness on national television.', 'category' => 'Media'],
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

    {{-- Photo Gallery placeholder --}}
    <section class="py-16 border-t" style="background: var(--tgf-light); border-color: var(--tgf-border);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10">
                <p class="text-sm font-semibold uppercase tracking-widest" style="color: var(--tgf-primary);">Photos</p>
                <h2 class="mt-2 text-2xl font-bold" style="color: var(--tgf-dark);">Campaign Highlights</h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $photos = [
                        ['gradient' => 'linear-gradient(135deg, #0D9488, #134E4A)', 'label' => 'World Thyroid Day 2023'],
                        ['gradient' => 'linear-gradient(135deg, #134E4A, #0F766E)', 'label' => 'Community Screening'],
                        ['gradient' => 'linear-gradient(135deg, #B45309, #D97706)', 'label' => 'Patient Forum Meeting'],
                        ['gradient' => 'linear-gradient(135deg, #0F172A, #1E3A5F)', 'label' => 'Annual Conference'],
                        ['gradient' => 'linear-gradient(135deg, #0F766E, #0D9488)', 'label' => 'Health Education Workshop'],
                        ['gradient' => 'linear-gradient(135deg, #1E3A5F, #0F172A)', 'label' => 'Northern Region Outreach'],
                        ['gradient' => 'linear-gradient(135deg, #D97706, #B45309)', 'label' => 'Fundraising Gala'],
                        ['gradient' => 'linear-gradient(135deg, #134E4A, #0D9488)', 'label' => 'Medication Distribution'],
                    ];
                @endphp
                @foreach($photos as $photo)
                    <div class="group relative aspect-square overflow-hidden rounded-xl" style="background: {{ $photo['gradient'] }};">
                        <div class="absolute inset-0 flex items-end p-4">
                            <span class="rounded-md px-3 py-1.5 text-xs font-medium text-white" style="background: rgba(0,0,0,0.5);">{{ $photo['label'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

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
