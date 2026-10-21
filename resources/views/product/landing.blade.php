@php
    $workspaceCalculator = true;
    $plans = config('usage-pricing.hybrid.website_plans', []);
@endphp
@extends('layouts.workspace-public')

@section('title')
Plans &amp; pricing — Unified Digital Workspace
@endsection

@push('styles')
<style>
    .pricing-intro { padding: 64px 48px; background: var(--paper-warm); }
    .pricing-intro h1 { max-width: 840px; font-size: clamp(36px, 5vw, 64px); line-height: 1.08; letter-spacing: -.04em; font-weight: 700; }
    .pricing-intro p { max-width: 720px; margin-top: 24px; color: var(--muted); font-size: 18px; }
    .pricing-plan-strip { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); background: var(--navy); color: white; }
    .pricing-plan { padding: 32px 48px; border-right: 1px solid rgba(255,255,255,.2); }
    .pricing-plan h2 { font-size: 22px; font-weight: 600; }
    .pricing-plan .amount { margin-top: 16px; font-size: 32px; font-weight: 700; letter-spacing: -.03em; }
    .pricing-plan .amount small { font-size: 15px; font-weight: 400; }
    .pricing-plan p { margin-top: 12px; color: #dbe8ff; }
    .pricing-guidance { padding: 48px; }
    .pricing-guidance h2 { font-size: 30px; font-weight: 700; letter-spacing: -.03em; }
    .pricing-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 32px; margin-top: 28px; }
    .pricing-steps h3 { font-size: 18px; font-weight: 600; }
    .pricing-steps p, .pricing-faqs p { margin-top: 12px; color: var(--muted); }
    .pricing-faqs { padding: 48px; background: var(--paper-warm); }
    .pricing-faqs h2 { font-size: 30px; font-weight: 700; margin-bottom: 24px; }
    .pricing-faqs details { padding: 20px 0; border-top: 1px solid var(--line); }
    .pricing-faqs summary { cursor: pointer; font-weight: 600; }
    .pricing-faqs summary:focus-visible { outline: 2px solid var(--blue); outline-offset: 4px; }
    @media (max-width: 720px) {
        .pricing-intro, .pricing-plan, .pricing-guidance, .pricing-faqs { padding: 32px 24px; }
        .pricing-plan-strip, .pricing-steps { grid-template-columns: minmax(0, 1fr); }
        .pricing-plan { border-right: none; border-bottom: 1px solid rgba(255,255,255,.2); }
    }
</style>
@endpush

@section('content')
    <section class="rail band pricing-intro" aria-labelledby="pricing-page-title">
        <div class="index-kicker">Plans &amp; pricing</div>
        <h1 id="pricing-page-title">A website first. A workspace when you need it.</h1>
        <p>Launch your website, publish updates and collect enquiries. Add the tools and member access your team needs as your work grows.</p>
        <div class="hero-actions">
            <a class="btn btn-lg" href="#pricing">Compare plans &amp; estimate costs</a>
            <a class="btn btn-lg btn-outline" href="{{ route('product.modules') }}">Explore workspace modules</a>
        </div>
    </section>

    <section class="rail band pricing-plan-strip" aria-label="Website plan starting prices">
        @foreach ($plans as $slug => $plan)
            <article class="pricing-plan">
                <h2>{{ $plan['name'] }}</h2>
                <div class="amount">GHS {{ number_format($plan['GHS'], 0) }} <small>/ USD {{ number_format($plan['USD'], 0) }} per month</small></div>
                <p>{{ $plan['editors'] }} website editors · {{ $plan['files_gib'] }} GiB file storage · {{ $plan['domains'] }} connected custom domain</p>
                <p>One-time standard setup: GHS {{ number_format($plan['setup']['GHS'], 0) }} / USD {{ number_format($plan['setup']['USD'], 0) }}.</p>
            </article>
        @endforeach
    </section>

    <section class="rail band pricing-guidance" aria-labelledby="billing-explained">
        <h2 id="billing-explained">How billing works</h2>
        <div class="pricing-steps">
            <article><h3>Start with your website</h3><p>Your website plan covers hosting, publishing, forms and included editors within its stated allowances. Domain registration and renewals are separate.</p></article>
            <article><h3>Add the work you need</h3><p>Choose operational modules and member access separately. Website-only editors are not counted again as operational users. See the calculator for access levels and usage limits.</p></article>
            <article><h3>Review before committing</h3><p>This calculator is an estimate. Setup fees appear separately, and applicable taxes or agreed services may be additional. Existing customer agreements keep their agreed rates.</p></article>
        </div>
    </section>

    <div class="rail band pricing-catalogue">
        <livewire:pricing-table />
    </div>

    <section class="rail band pricing-faqs" aria-labelledby="pricing-questions">
        <h2 id="pricing-questions">Before you choose a plan</h2>
        @foreach (config('product-page.faqs', []) as $faq)
            <details><summary>{{ $faq['q'] }}</summary><p>{{ $faq['a'] }}</p></details>
        @endforeach
    </section>

    <section class="rail band closing-grid" aria-labelledby="pricing-next-step">
        <div class="closing-left"><h2 id="pricing-next-step">Plan a workspace around your work.</h2><p>Tell us about your website, team and operational needs so we can agree the right setup.</p></div>
        <div class="closing-right"><a class="btn btn-lg" href="{{ route('product.enterprise') }}">Plan your workspace</a><a class="btn btn-lg btn-outline" href="{{ route('product.modules') }}">Explore modules</a></div>
    </section>
@endsection
