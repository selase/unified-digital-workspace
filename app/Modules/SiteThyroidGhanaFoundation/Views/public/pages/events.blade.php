@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Events — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Events — ' . $theme->siteName(),
        'description' => 'Screening days, awareness events and forums run by the Thyroid Ghana Foundation.',
        'canonical' => $cmsUrl->route('pages.show', 'events'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'slug' => 'events',
        'crumb' => 'Events',
        'title' => 'Events',
        'intro' => 'Screening days, awareness events and forums. Come along, bring a friend, or volunteer to help.',
    ])

    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Upcoming events</h2>

            @forelse($upcoming as $event)
                @include('site-thyroid-ghana-foundation::public.pages._event', ['event' => $event])
            @empty
                <div class="mt-6 rounded-2xl bg-white p-8 shadow-sm">
                    <p style="color: var(--tgf-text);">No events are scheduled right now. Follow us on <a href="https://www.facebook.com/thyroidghana" class="font-semibold underline" style="color: var(--tgf-primary-dark);" rel="noopener">Facebook</a> or <a href="{{ $cmsUrl->route('pages.show', 'contact') }}" class="font-semibold underline" style="color: var(--tgf-primary-dark);">contact us</a> to hear about the next one.</p>
                </div>
            @endforelse

            @if($past->isNotEmpty())
                <h2 class="mt-16 text-2xl font-bold" style="color: var(--tgf-dark);">Past events</h2>
                @foreach($past as $event)
                    @include('site-thyroid-ghana-foundation::public.pages._event', ['event' => $event])
                @endforeach
            @endif
        </div>
    </section>

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
