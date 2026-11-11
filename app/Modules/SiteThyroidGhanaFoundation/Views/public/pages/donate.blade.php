@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Support our cause — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Support our cause — ' . $theme->siteName(),
        'description' => 'Your gift helps more patients reach early diagnosis and affordable thyroid treatment in Ghana.',
        'canonical' => $cmsUrl->route('pages.show', 'donate'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'crumb' => 'Donate',
        'title' => 'Support our cause',
        'intro' => 'Your gift helps more patients reach early diagnosis and affordable treatment.',
    ])

    <section class="py-16 lg:py-20">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
            <div class="lg:col-span-2">
                <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Where your gift goes</h2>
                <ul class="mt-6 space-y-5">
                    @foreach([
                        ['Free screenings and education', 'Community thyroid screening days and awareness work.'],
                        ['Support for patients', 'A patients forum and group that no one has to face alone.'],
                        ['Subsidised surgery', 'Helping patients who cannot afford the operation they need.'],
                    ] as [$heading, $text])
                        <li class="flex gap-4">
                            <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background: var(--tgf-primary-light); color: var(--tgf-primary-dark);">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span><span class="block font-semibold" style="color: var(--tgf-dark);">{{ $heading }}</span><span class="text-sm" style="color: var(--tgf-muted);">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lg:col-span-3">
                @if($online)
                    <form method="POST" action="{{ route($cmsUrl->isWebsiteMode() ? 'site-thyroid-ghana-foundation.website.donate.store' : 'site-thyroid-ghana-foundation.public.donate.store') }}" class="rounded-2xl bg-white p-6 shadow-lg sm:p-8" x-data="{ amount: '{{ old('amount', 100) }}' }">
                        @csrf
                        <h3 class="text-xl font-bold" style="color: var(--tgf-dark);">Make a gift</h3>
                        <p class="mt-1 text-sm" style="color: var(--tgf-muted);">You will pay securely online and we will email your receipt.</p>
                        <div class="mt-6">@include('site-thyroid-ghana-foundation::public.pages._errors')</div>
                        <fieldset>
                            <legend class="block text-sm font-semibold" style="color: var(--tgf-dark);">Amount (GHS)</legend>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach([50, 100, 250, 500] as $preset)
                                    <button type="button" x-on:click="amount = '{{ $preset }}'" class="rounded-full px-5 py-2 text-sm font-semibold transition" x-bind:class="amount == '{{ $preset }}' ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">{{ $preset }}</button>
                                @endforeach
                            </div>
                            <input name="amount" type="number" min="1" step="1" required x-model="amount" aria-label="Amount in GHS" class="mt-3 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                        </fieldset>
                        @if($campaigns->isNotEmpty())
                            <div class="mt-5">
                                <label for="campaign_id" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Direct your gift</label>
                                <select id="campaign_id" name="campaign_id" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                                    <option value="">Where it is needed most</option>
                                    @foreach($campaigns as $campaign)
                                        <option value="{{ $campaign->id }}" @selected((int) old('campaign_id') === $campaign->id)>{{ $campaign->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Your name</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                            </div>
                        </div>
                        <div class="absolute -left-[9999px]" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                        <button type="submit" class="tgf-btn-accent mt-6 w-full text-center">Give securely online</button>
                    </form>
                @else
                    <div class="rounded-2xl bg-white p-6 shadow-lg sm:p-8">
                        <h3 class="text-xl font-bold" style="color: var(--tgf-dark);">Give by bank transfer or mobile money</h3>
                        <p class="mt-2 text-sm leading-relaxed" style="color: var(--tgf-muted);">Contact us and we will send you our account details, and a receipt once your gift arrives.</p>
                        <dl class="mt-6 space-y-3 text-sm">
                            <div class="flex gap-3"><dt class="w-16 font-semibold" style="color: var(--tgf-dark);">Phone</dt><dd><a href="tel:+233243376304" class="underline" style="color: var(--tgf-primary-dark);">+233 (024) 337 6304</a></dd></div>
                            <div class="flex gap-3"><dt class="w-16 font-semibold" style="color: var(--tgf-dark);">Email</dt><dd><a href="mailto:info@thyroidghanafoundation.org" class="underline" style="color: var(--tgf-primary-dark);">info@thyroidghanafoundation.org</a></dd></div>
                        </dl>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @include('site-thyroid-ghana-foundation::public.pages._questions', ['type' => 'donation', 'subject' => 'Donation enquiry'])

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
