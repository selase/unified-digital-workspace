@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Partner with us — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Partner with us — ' . $theme->siteName(),
        'description' => 'Work with the Thyroid Ghana Foundation to bring screening, education and care to more people.',
        'canonical' => $cmsUrl->route('pages.show', 'partner-us'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'crumb' => 'Partner with us',
        'title' => 'Partner with us',
        'intro' => 'Hospitals, companies and organisations help us bring screening, education and affordable care to more people.',
    ])

    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Ways to work together</h2>
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach([
                    ['Sponsor a screening day', 'Fund the tests, supplies and logistics for a community screening and education day.'],
                    ['Support subsidised surgery', 'Help patients who cannot afford surgery reach the care they need.'],
                    ['Share your expertise', 'Collaborate on awareness, training and research with doctors and institutions.'],
                ] as [$heading, $text])
                    <article class="relative overflow-hidden rounded-2xl bg-white p-8 shadow-sm transition hover:shadow-xl">
                        <div class="absolute inset-x-0 top-0 h-1.5" style="background: var(--tgf-primary);"></div>
                        <h3 class="text-lg font-bold" style="color: var(--tgf-dark);">{{ $heading }}</h3>
                        <p class="mt-3 text-sm leading-relaxed" style="color: var(--tgf-text);">{{ $text }}</p>
                    </article>
                @endforeach
            </div>
            <div class="mx-auto max-w-3xl">
                @include('cms-core::components.enquiry-form', ['type' => 'partnership'])
            </div>
        </div>
    </section>

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
