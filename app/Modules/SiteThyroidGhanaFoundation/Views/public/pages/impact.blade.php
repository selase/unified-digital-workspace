@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Our Impact — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Our Impact — ' . $theme->siteName(),
        'description' => 'What the Thyroid Ghana Foundation has done for thyroid patients in Ghana and across West Africa.',
        'canonical' => url('/our-impact'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'crumb' => 'Our Impact',
        'title' => 'Our Impact',
        'intro' => 'Where we have worked, whom we have served and how we help thyroid patients.',
    ])

    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($figures as $figure)
                    <div class="relative overflow-hidden rounded-2xl bg-white p-8 shadow-sm">
                        <div class="absolute inset-x-0 top-0 h-1.5" style="background: var(--tgf-primary);"></div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-lg" style="background: var(--tgf-primary-light);">
                            <svg width="24" height="24" style="color: var(--tgf-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $figure['icon'] }}"/></svg>
                        </div>
                        <p class="mt-6 text-3xl font-extrabold" style="color: var(--tgf-primary-dark);">{{ $figure['value'] }}</p>
                        <p class="mt-1 text-sm" style="color: var(--tgf-muted);">{{ $figure['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 lg:py-20" style="background: var(--tgf-light);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">How we help</h2>
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($pillars as $pillar)
                    <article class="rounded-2xl bg-white p-8 shadow-sm transition hover:shadow-xl">
                        <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-lg" style="background: var(--tgf-primary-light);">
                            <svg width="24" height="24" style="color: var(--tgf-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $pillar['icon'] }}"/></svg>
                        </div>
                        <h3 class="text-lg font-semibold" style="color: var(--tgf-dark);">{{ $pillar['title'] }}</h3>
                        <p class="mt-3 text-sm leading-relaxed" style="color: var(--tgf-muted);">{{ $pillar['desc'] }}</p>
                    </article>
                @endforeach
            </div>
            <p class="mt-10 text-sm" style="color: var(--tgf-muted);">Read what comes next in <a href="{{ $cmsUrl->route('pages.show', 'our-plan') }}" class="font-semibold underline" style="color: var(--tgf-primary-dark);">our plan</a>.</p>
        </div>
    </section>

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
