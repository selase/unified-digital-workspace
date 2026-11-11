@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', 'Become a member — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Become a member — ' . $theme->siteName(),
        'description' => 'Join the Thyroid Ghana Foundation and stand with thyroid patients across Ghana.',
        'canonical' => $cmsUrl->route('pages.show', 'membership'),
    ])
@endsection

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', [
        'crumb' => 'Membership',
        'title' => 'Become a member',
        'intro' => 'Stand with thyroid patients across Ghana. Your membership helps us run free screenings, patient education and subsidised surgery.',
    ])

    @if($tiers->isNotEmpty())
        <section class="py-16 lg:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Choose your membership</h2>
                <p class="mt-2 max-w-2xl" style="color: var(--tgf-muted);">Every membership runs for a year and can be renewed online. Pick the one that fits you.</p>

                <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($tiers as $tier)
                        <article class="relative flex flex-col overflow-hidden rounded-2xl bg-white p-8 shadow-sm transition hover:shadow-xl">
                            <div class="absolute inset-x-0 top-0 h-1.5" style="background: var(--tgf-primary);"></div>
                            <h3 class="text-lg font-bold" style="color: var(--tgf-dark);">{{ $tier->name }}</h3>
                            <p class="mt-5 flex flex-wrap items-baseline gap-x-2">
                                <span class="text-4xl font-extrabold" style="color: var(--tgf-primary-dark);">{{ $tier->fee === 0 ? 'Free' : 'GHS '.number_format($tier->fee / 100, $tier->fee % 100 === 0 ? 0 : 2) }}</span>
                                @if($tier->fee > 0)
                                    <span class="text-sm" style="color: var(--tgf-muted);">{{ $tier->period_months === 12 ? 'per year' : 'every '.$tier->period_months.' months' }}</span>
                                @endif
                            </p>
                            @if($tier->description)
                                <p class="mt-4 flex-1 text-sm leading-relaxed" style="color: var(--tgf-text);">{{ $tier->description }}</p>
                            @endif
                            <a href="{{ request()->url() }}?type={{ $tier->id }}#join" class="tgf-btn-accent mt-8 text-center">Join as {{ $tier->name }}</a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="join" class="scroll-mt-24 py-16 lg:py-20" style="background: var(--tgf-light);">
            <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-5 lg:px-8">
                <div class="lg:col-span-2">
                    <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">What your membership supports</h2>
                    <ul class="mt-6 space-y-5">
                        @foreach([
                            ['Free community screenings', 'Thyroid screening and education days in health centres.'],
                            ['Patient education and support', 'A patients forum and a WhatsApp support group with medical practitioners.'],
                            ['Subsidised thyroid surgery', 'Helping patients who cannot afford the operation they need.'],
                        ] as [$heading, $text])
                            <li class="flex gap-4">
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background: var(--tgf-primary-light); color: var(--tgf-primary-dark);">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                </span>
                                <span><span class="block font-semibold" style="color: var(--tgf-dark);">{{ $heading }}</span><span class="text-sm" style="color: var(--tgf-muted);">{{ $text }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                    @if($portalOn)
                        <p class="mt-8 text-sm" style="color: var(--tgf-muted);">Already a member? <a href="{{ mb_rtrim((string) config('app.url'), '/') }}/join/{{ $tenantSlug }}/portal" class="font-semibold underline" style="color: var(--tgf-primary-dark);">Sign in</a> to see or renew your membership.</p>
                    @endif
                </div>

                <div class="lg:col-span-3">
                    <form method="POST" action="{{ route($cmsUrl->isWebsiteMode() ? 'site-thyroid-ghana-foundation.website.membership.store' : 'site-thyroid-ghana-foundation.public.membership.store') }}" class="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                        @csrf
                        <h3 class="text-xl font-bold" style="color: var(--tgf-dark);">Apply for membership</h3>
                        <p class="mt-1 text-sm" style="color: var(--tgf-muted);">We will contact you about your membership.</p>
                        <div class="mt-6">@include('site-thyroid-ghana-foundation::public.pages._errors')</div>

                        <div class="space-y-5">
                            <div>
                                <label for="type_id" class="block text-sm font-semibold" style="color: var(--tgf-dark);">Membership</label>
                                <select id="type_id" name="type_id" required class="mt-1.5 w-full rounded-lg bg-slate-50 px-4 py-3 text-sm ring-1 ring-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-600">
                                    @foreach($tiers as $tier)
                                        <option value="{{ $tier->id }}" @selected((int) old('type_id', $selectedType) === $tier->id)>{{ $tier->name }}{{ $tier->fee > 0 ? ' — GHS '.number_format($tier->fee / 100, $tier->fee % 100 === 0 ? 0 : 2) : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
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
                            <div class="absolute -left-[9999px]" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <button type="submit" class="tgf-btn-accent w-full text-center">Apply for membership</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    @else
        <section class="py-16 lg:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <p class="text-lg" style="color: var(--tgf-text);">Membership details are being finalised. Tell us you are interested and we will contact you as soon as joining opens.</p>
            </div>
        </section>
    @endif

    @include('site-thyroid-ghana-foundation::public.pages._questions', ['type' => 'membership', 'subject' => 'Membership enquiry'])

    @include('site-thyroid-ghana-foundation::public.pages._cta')
@endsection
