@extends('layouts.product')

@section('content')
    {{-- HERO --}}
    <section class="pt-16 md:pt-24">
        <div class="mx-auto max-w-6xl px-6 text-center">
            <h1
                class="mx-auto max-w-3xl whitespace-pre-line text-[52px] leading-[52px] font-medium tracking-[-0.02em] text-white md:text-[96px] md:leading-[96px]">
                {{ config('product-page.hero.title') }}
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-[20px] leading-[28px] font-normal text-white/55">
                {{ config('product-page.hero.subtitle') }}
            </p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ config('product-page.hero.cta_primary.href') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm shadow-blue-600/20 hover:bg-blue-500 transition">
                    {{ config('product-page.hero.cta_primary.label') }}
                </a>
                <a href="{{ config('product-page.hero.cta_secondary.href') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    {{ config('product-page.hero.cta_secondary.label') }}
                </a>
            </div>

            <div class="mt-12 rounded-2xl border border-white/10 bg-white/[0.03] p-2 shadow-2xl shadow-black/40">
                <div class="overflow-hidden rounded-xl border border-white/10 bg-black/30">
                    @if (config('product-page.hero.media.type') === 'video')
                        <video class="w-full" autoplay muted loop playsinline>
                            <source src="{{ config('product-page.hero.media.src') }}" type="video/mp4">
                        </video>
                    @else
                        <img src="{{ config('product-page.hero.media.src') }}" alt="{{ config('product-page.hero.media.alt') }}"
                            class="w-full object-cover" />
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- INTRO --}}
    <section class="mt-20 md:mt-28">
        <div class="mx-auto max-w-6xl px-6">
            <div class="grid gap-10 md:grid-cols-2 md:items-start">
                <div>
                    <div
                        class="inline-flex items-center rounded-full border border-blue-500/20 bg-blue-500/10 px-3 py-1 text-xs font-medium text-blue-200">
                        {{ config('product-page.intro.eyebrow') }}
                    </div>

                    <h2
                        class="mt-5 max-w-xl text-[40px] leading-[40px] font-medium tracking-[-0.01em] text-white/95 md:text-[48px] md:leading-[48px]">
                        {{ config('product-page.intro.title') }}
                    </h2>

                    <p class="mt-5 max-w-xl text-[18px] leading-[28px] font-light text-white/70">
                        {{ config('product-page.intro.body') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (config('product-page.intro.cards') as $c)
                        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                            <div class="text-xs font-medium text-white/40">{{ $c['kicker'] }}</div>
                            <div class="mt-2 text-[18px] leading-[28px] font-medium text-white/95">{{ $c['title'] }}</div>
                            <div class="mt-2 text-[16px] leading-[24px] font-light text-white/55">{{ $c['body'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- CAPABILITIES --}}
    <section class="mt-20 md:mt-28">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2
                    class="text-[40px] leading-[40px] font-medium tracking-[-0.01em] text-white/95 md:text-[48px] md:leading-[48px]">
                    {{ config('product-page.capabilities.title') }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-[18px] leading-[28px] font-light text-white/70">
                    {{ config('product-page.capabilities.subtitle') }}
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (config('product-page.capabilities.items') as $it)
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/[0.05] transition">
                        <div class="flex items-start gap-3">
                            <div
                                class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 bg-white/5 text-sm text-blue-200">
                                {{ $it['icon'] }}
                            </div>
                            <div>
                                <div class="text-[18px] leading-[28px] font-medium text-white/95">{{ $it['title'] }}</div>
                                <div class="mt-1 text-[16px] leading-[24px] font-light text-white/55">{{ $it['body'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- PRICING --}}
    <livewire:pricing-table />

    {{-- TESTIMONIALS --}}
    <section class="mt-20 md:mt-28">
        <div class="mx-auto max-w-6xl px-6">
            <div class="grid gap-8 md:grid-cols-2">
                @foreach (config('product-page.testimonials') as $t)
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] px-6 py-10">
                        <div class="text-blue-300 text-2xl leading-none">“</div>
                        <p class="mt-4 text-[18px] leading-[28px] font-light text-white/80">
                            {{ $t['quote'] }}
                        </p>
                        <div class="mt-6 flex items-center gap-3">
                            <div class="h-9 w-9 rounded-full bg-white/10 overflow-hidden">
                                @if($t['name'] === 'Alex Rivera')
                                    <img src="/assets/img/marketing/avatar-alex.png" class="w-full h-full object-cover">
                                @else
                                    <img src="/assets/img/marketing/avatar-sarah.png" class="w-full h-full object-cover">
                                @endif
                            </div>
                            <div>
                                <div class="text-sm font-medium text-white/90">{{ $t['name'] }}</div>
                                <div class="text-xs text-white/50">{{ $t['role'] }} · {{ $t['company'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQS --}}
    <section class="mt-20 md:mt-28">
        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <h2
                    class="text-[40px] leading-[40px] font-medium tracking-[-0.01em] text-white/95 md:text-[48px] md:leading-[48px]">
                    Frequently asked questions
                </h2>
            </div>

            <div class="mx-auto mt-10 max-w-3xl space-y-3">
                @foreach (config('product-page.faqs') as $faq)
                    <details class="group rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                            <span class="text-sm font-medium text-white/85">{{ $faq['q'] }}</span>
                            <span
                                class="grid h-8 w-8 place-items-center rounded-lg border border-white/10 bg-white/5 text-white/60 group-open:rotate-45 transition">+</span>
                        </summary>
                        <div class="mt-3 text-[16px] leading-[24px] font-light text-white/55">
                            {{ $faq['a'] }}
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="mt-20 md:mt-28 pb-20">
        <div class="mx-auto max-w-6xl px-6">
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center md:p-12">
                <h2
                    class="mx-auto max-w-3xl text-[32px] leading-[36px] font-medium tracking-[-0.01em] text-white/95 md:text-[40px] md:leading-[44px]">
                    {{ config('product-page.final_cta.title') }}
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-[18px] leading-[28px] font-light text-white/70">
                    {{ config('product-page.final_cta.subtitle') }}
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ config('product-page.final_cta.cta_primary.href') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm shadow-blue-600/20 hover:bg-blue-500 transition">
                        {{ config('product-page.final_cta.cta_primary.label') }}
                    </a>
                    <a href="{{ config('product-page.final_cta.cta_secondary.href') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        {{ config('product-page.final_cta.cta_secondary.label') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection