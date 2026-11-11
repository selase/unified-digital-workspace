@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Our Board — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Our Board — ' . $theme->siteName(),
        'description' => 'The management board and advisory board guiding the Thyroid Ghana Foundation.',
        'canonical' => url('/our-board'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'crumb' => 'Our Board',
        'title' => 'Our Board',
        'intro' => 'The people who guide the foundation\'s direction and hold it to account.',
    ])

    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Management Board</h2>

            <div class="mt-8 grid gap-8 lg:grid-cols-5">
                <article class="overflow-hidden rounded-2xl bg-white shadow-sm lg:col-span-3 sm:flex">
                    <div class="aspect-square shrink-0 overflow-hidden bg-slate-100 sm:w-64">
                        <img src="{{ Storage::disk('public')->url($board['chair']['photo']) }}" alt="{{ $board['chair']['name'] }}" class="h-full w-full object-cover object-top" loading="lazy">
                    </div>
                    <div class="p-8">
                        <p class="text-sm font-semibold" style="color: var(--tgf-primary);">{{ $board['chair']['role'] }}</p>
                        <h3 class="mt-1 text-xl font-bold" style="color: var(--tgf-dark);">{{ $board['chair']['name'] }}</h3>
                        <p class="mt-4 text-sm leading-relaxed" style="color: var(--tgf-text);">{{ $board['chair']['bio'] }}</p>
                    </div>
                </article>

                <div class="grid gap-6 lg:col-span-2">
                    @foreach($board['members'] as $member)
                        <article class="flex items-center gap-5 rounded-2xl bg-white p-6 shadow-sm">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-bold" style="background: var(--tgf-primary-light); color: var(--tgf-primary-dark);">{{ collect(explode(' ', $member['name']))->map(fn ($word) => mb_substr($word, 0, 1))->slice(0, 2)->join('') }}</span>
                            <span>
                                <span class="block text-lg font-semibold" style="color: var(--tgf-dark);">{{ $member['name'] }}</span>
                                <span class="block text-sm" style="color: var(--tgf-primary);">{{ $member['role'] }}</span>
                            </span>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 lg:py-20" style="background: var(--tgf-light);">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Advisory Board</h2>
            <article class="mt-8 flex max-w-xl items-center gap-5 rounded-2xl bg-white p-6 shadow-sm">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-bold" style="background: var(--tgf-primary-light); color: var(--tgf-primary-dark);">{{ collect(explode(' ', $board['advisoryChair']['name']))->map(fn ($word) => mb_substr($word, 0, 1))->slice(0, 2)->join('') }}</span>
                <span>
                    <span class="block text-lg font-semibold" style="color: var(--tgf-dark);">{{ $board['advisoryChair']['name'] }}</span>
                    <span class="block text-sm" style="color: var(--tgf-primary);">{{ $board['advisoryChair']['role'] }}</span>
                </span>
            </article>
            <p class="mt-8 text-sm" style="color: var(--tgf-muted);">Meet the people who run the foundation day to day on <a href="{{ $cmsUrl->route('pages.show', 'our-team') }}" class="font-semibold underline" style="color: var(--tgf-primary-dark);">our team page</a>.</p>
        </div>
    </section>

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
