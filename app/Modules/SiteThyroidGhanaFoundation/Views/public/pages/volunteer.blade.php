@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Volunteer with us — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Volunteer with us — ' . $theme->siteName(),
        'description' => 'Give your time to thyroid health awareness and patient support in Ghana.',
        'canonical' => $cmsUrl->route('pages.show', 'volunteer'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'crumb' => 'Volunteer',
        'title' => 'Volunteer with us',
        'intro' => 'Join the people who run our screening days, support groups and awareness work.',
    ])

    @if($moduleOn)
        @if($opportunities->isNotEmpty())
            <section class="py-16 lg:py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Where we need help</h2>
                    <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($opportunities as $opportunity)
                            <article class="rounded-2xl bg-white p-6 shadow-sm transition hover:shadow-xl">
                                <p class="text-sm font-semibold" style="color: var(--tgf-accent);">{{ $opportunity->starts_on->format('j F Y') }}</p>
                                <h3 class="mt-2 text-lg font-bold" style="color: var(--tgf-dark);">{{ $opportunity->title }}</h3>
                                @if($opportunity->location)
                                    <p class="mt-1 text-sm" style="color: var(--tgf-muted);">{{ $opportunity->location }}</p>
                                @endif
                                @if($opportunity->description)
                                    <p class="mt-3 text-sm leading-relaxed" style="color: var(--tgf-text);">{{ $opportunity->description }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section id="sign-up" class="scroll-mt-24 py-16 lg:py-20" style="background: var(--tgf-light);">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form method="POST" action="{{ route($cmsUrl->isWebsiteMode() ? 'site-thyroid-ghana-foundation.website.volunteer.store' : 'site-thyroid-ghana-foundation.public.volunteer.store') }}" class="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                    @csrf
                    <h2 class="text-xl font-bold" style="color: var(--tgf-dark);">Offer your time</h2>
                    <p class="mt-1 text-sm" style="color: var(--tgf-muted);">We review every sign-up and get in touch about the next step.</p>
                    <div class="mt-6">@include('site-thyroid-ghana-foundation::public.pages._errors')</div>
                    <div class="space-y-5">
                        <div>
                            <label for="name" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Your name</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="email" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Phone <span class="font-normal" style="color: var(--tgf-muted);">(optional)</span></label>
                                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                            </div>
                        </div>
                        <div>
                            <label for="skills" class="block text-sm font-semibold" style="color: var(--tgf-dark);">How can you help? <span class="font-normal" style="color: var(--tgf-muted);">(optional)</span></label>
                            <textarea id="skills" name="skills" rows="3" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">{{ old('skills') }}</textarea>
                        </div>
                        <div class="absolute -left-[9999px]" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <button type="submit" class="tgf-btn-accent w-full text-center">Sign up to volunteer</button>
                    </div>
                    <p class="mt-5 text-sm" style="color: var(--tgf-muted);">Already volunteering? <a href="{{ url('/volunteer/'.$tenantSlug.'/portal') }}" class="font-semibold underline" style="color: var(--tgf-primary-dark);">Sign in</a> to see your hours.</p>
                </form>
            </div>
        </section>
    @endif

    @include('site-thyroid-ghana-foundation::public.pages._questions', ['type' => 'volunteer', 'subject' => 'Volunteer enquiry'])

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
