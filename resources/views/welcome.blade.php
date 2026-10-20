@php
    $pricingUrl = route('product.template').'#pricing';
    $hasRegistration = \Illuminate\Support\Facades\Route::has('register') && config('app.system_setting.allow_registration', false);
    $hasEnquiry = \Illuminate\Support\Facades\Route::has('product.enterprise');
    $hasLogin = \Illuminate\Support\Facades\Route::has('login');
    $websitePlans = config('usage-pricing.hybrid.website_plans', []);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bring projects, documents, people and public websites into one Unified Digital Workspace. Explore the tools for your team's everyday work.">
    <meta name="theme-color" content="#173a75">
    <title>Unified Digital Workspace — A place for your work</title>
    <style>
        /* Homepage styles are self-contained; tenant sites and shared layouts are unaffected. */
        :root { color-scheme: light; --ink: #172a46; --muted: #52627b; --blue: #2455b5; --navy: #173a75; --paper: #f2f6fc; --line: #d7e0ed; --green: #20634d; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: var(--ink); font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        a:hover { text-decoration: underline; text-underline-offset: .25em; }
        a:focus-visible { outline: 3px solid #bc4c19; outline-offset: 5px; border-radius: 3px; }
        h1, h2, h3, p, figure { margin: 0; }
        h1, h2 { text-wrap: balance; }
        p { text-wrap: pretty; }
        .wrap { width: min(1240px, calc(100% - 80px)); margin-inline: auto; }
        .skip { position: absolute; z-index: 10; left: 16px; top: -100px; padding: 12px 20px; background: white; }
        .skip:focus { top: 12px; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding-block: 26px; border-bottom: 1px solid var(--line); }
        .brand { display: inline-flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 750; line-height: 1.25; }
        .brand-mark { display: grid; grid-template-columns: repeat(2, 10px); gap: 3px; padding: 10px; background: var(--navy); border-radius: 10px 10px 3px 10px; }
        .brand-mark i { width: 10px; height: 10px; background: #fff; border-radius: 2px; }
        .brand-mark i:last-child { background: #a8c5ff; }
        .nav, .account, .actions { display: flex; align-items: center; gap: 24px; }
        .nav, .account { font-size: 14px; font-weight: 600; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 13px 21px; border: 1px solid var(--blue); background: var(--blue); color: white; font-size: 14px; font-weight: 650; border-radius: 6px; line-height: 1.4; }
        .button:hover { background: #1c4390; text-decoration: none; }
        .button.secondary { background: white; color: var(--ink); border-color: var(--line); }
        .button.secondary:hover { background: var(--paper); }
        .hero { display: grid; grid-template-columns: .9fr 1.1fr; gap: 56px; align-items: center; padding-block: 80px 70px; }
        .intro-note { display: flex; gap: 9px; align-items: center; color: var(--navy); font-size: 13px; font-weight: 650; margin-bottom: 24px; }
        .intro-note::before { content: ''; width: 7px; height: 7px; background: var(--blue); border-radius: 50%; }
        h1 { font-family: Georgia, "Times New Roman", serif; font-weight: 400; font-size: clamp(48px, 5.1vw, 72px); line-height: 1.04; letter-spacing: -.045em; max-width: 560px; }
        .hero-copy > p { max-width: 460px; margin-top: 26px; color: var(--muted); font-size: 18px; line-height: 1.75; }
        .actions { gap: 12px; flex-wrap: wrap; margin-top: 30px; }
        .hero-copy .availability { font-size: 12px; margin-top: 15px; }
        .workbench { min-width: 0; position: relative; padding: 22px 0 0 22px; }
        .workbench::before { content: ''; position: absolute; inset: 0 20px 24px 0; z-index: -1; background: #e7effc; border-radius: 18px; }
        .window { background: white; border: 1px solid #bfcde1; border-radius: 10px; overflow: hidden; box-shadow: 0 16px 44px #173a7512; }
        .window-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--line); font-size: 11px; color: var(--muted); }
        .window-dots { display: flex; gap: 4px; }
        .window-dots i { width: 6px; height: 6px; border-radius: 50%; background: #b2bed1; }
        .window-body { display: grid; grid-template-columns: 112px minmax(0, 1fr); min-height: 360px; }
        .preview-sidebar { padding: 23px 12px; background: var(--paper); font-size: 11px; }
        .preview-sidebar strong { display: block; padding-inline: 8px; margin-bottom: 22px; color: var(--navy); }
        .preview-sidebar ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
        .preview-sidebar li { padding: 7px 8px; border-radius: 4px; }
        .preview-sidebar .selected { background: #dfe9fc; color: #21458e; font-weight: 650; }
        .preview-content { padding: 24px 20px; min-width: 0; }
        .preview-content h2 { font-size: 21px; letter-spacing: -.04em; }
        .preview-subtitle { color: var(--muted); font-size: 11px; margin-top: 3px; }
        .queue-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 25px; padding-bottom: 9px; border-bottom: 1px solid var(--line); font-size: 11px; }
        .queue-heading span { color: var(--muted); font-size: 10px; }
        .queue { list-style: none; padding: 0; margin: 0; }
        .queue li { padding-block: 13px; border-bottom: 1px solid #e9eef6; display: grid; grid-template-columns: 21px 1fr auto; align-items: center; gap: 7px; font-size: 11px; }
        .queue-icon { display: grid; place-items: center; width: 20px; height: 23px; border-radius: 3px; background: #ebeffa; color: var(--blue); font-size: 12px; }
        .queue small { display: block; color: var(--muted); font-size: 10px; }
        .status { font-size: 9px; padding: 3px 6px; color: #775016; background: #fff3d7; border-radius: 3px; white-space: nowrap; }
        .status.ready { color: var(--green); background: #e5f2eb; }
        .preview-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .preview-tile { border: 1px solid var(--line); border-radius: 5px; padding: 12px; font-size: 10px; }
        .preview-tile strong { display: block; font-size: 11px; margin-bottom: 5px; }
        .preview-tile p { color: var(--muted); }
        figcaption { text-align: right; color: var(--muted); font-size: 11px; margin-top: 12px; }
        .tool-strip { padding-block: 22px; border-block: 1px solid var(--line); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px 24px; font-size: 13px; }
        .tool-strip > span { color: var(--muted); }
        .tool-strip ul { display: flex; flex-wrap: wrap; gap: 14px 28px; list-style: none; padding: 0; margin: 0; font-weight: 600; }
        .capabilities { padding-block: 84px; }
        .section-intro { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: end; margin-bottom: 42px; }
        h2 { font-size: clamp(29px, 3vw, 40px); font-weight: 550; line-height: 1.2; letter-spacing: -.04em; }
        .section-intro p { max-width: 490px; color: var(--muted); font-size: 16px; }
        .capability-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 30px; }
        .capability { border-top: 3px solid var(--line); padding-top: 24px; }
        .capability:first-child { border-color: var(--blue); }
        .capability h3 { font-size: 18px; font-weight: 650; margin-bottom: 13px; letter-spacing: -.025em; }
        .capability p { font-size: 14px; color: var(--muted); line-height: 1.8; }
        .capability .tools { font-size: 11px; color: var(--navy); margin-top: 21px; font-weight: 600; }
        .pricing { background: var(--navy); color: white; border-radius: 12px; padding: 48px; display: grid; grid-template-columns: 1.1fr 1fr; gap: 50px; align-items: center; }
        .pricing h2 { max-width: 390px; }
        .pricing p { color: #d3e0f7; font-size: 14px; margin-top: 18px; max-width: 460px; }
        .pricing .button { background: white; border-color: white; color: var(--navy); margin-top: 25px; }
        .pricing .button:hover { background: #e5edfb; }
        .plan { display: flex; justify-content: space-between; gap: 20px; align-items: baseline; border-bottom: 1px solid #ffffff38; padding-block: 18px; font-size: 14px; }
        .plan:first-child { padding-top: 0; }
        .plan strong { font-size: 24px; font-weight: 550; white-space: nowrap; letter-spacing: -.04em; }
        .plan small { font-size: 11px; font-weight: 400; letter-spacing: normal; color: #d3e0f7; }
        .pricing .price-note { font-size: 11px; line-height: 1.8; }
        .closing { padding-block: 80px; display: flex; justify-content: space-between; gap: 30px; align-items: center; }
        .closing h2 { font-family: Georgia, "Times New Roman", serif; font-weight: 400; }
        .closing p { color: var(--muted); margin-top: 12px; font-size: 15px; }
        .closing .actions { margin-top: 0; flex-shrink: 0; }
        footer { border-top: 1px solid var(--line); padding-block: 25px; color: var(--muted); font-size: 12px; display: flex; justify-content: space-between; gap: 20px; }
        footer nav { display: flex; gap: 24px; }
        @media (min-width: 800px) and (prefers-reduced-motion: no-preference) { .window { animation: arrive .5s ease-out both; } @keyframes arrive { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } } }
        @media (max-width: 1080px) { .hero { gap: 30px; } .nav { gap: 18px; } .account { gap: 14px; } .preview-sidebar { display: none; } .window-body { grid-template-columns: minmax(0, 1fr); } .capability-grid { gap: 22px; } }
        @media (max-width: 800px) { .wrap { width: calc(100% - 40px); } .header { flex-wrap: wrap; gap: 20px; padding-block: 20px; } .nav { order: 3; width: 100%; justify-content: space-between; } .account { margin-left: auto; } .hero { grid-template-columns: 1fr; padding-block: 48px; gap: 38px; } h1 { max-width: 600px; font-size: clamp(48px, 9vw, 70px); } .hero-copy > p { max-width: 600px; } .workbench { max-width: 630px; width: 100%; } .preview-sidebar { display: block; } .window-body { grid-template-columns: 112px minmax(0, 1fr); } .section-intro, .pricing { grid-template-columns: 1fr; gap: 26px; } .capabilities { padding-block: 56px; } .capability-grid { grid-template-columns: 1fr 1fr; gap: 32px; } .pricing { padding: 32px; } .closing { padding-block: 56px; align-items: flex-start; flex-direction: column; } }
        @media (max-width: 440px) { .wrap { width: calc(100% - 32px); } .brand { font-size: 11px; gap: 8px; } .brand-mark { padding: 8px; } .account { font-size: 12px; gap: 12px; } .account .button { padding: 10px 12px; font-size: 12px; } .hero-copy > p { font-size: 16px; } .workbench { padding-left: 10px; padding-top: 12px; } .preview-sidebar { display: none; } .window-body { grid-template-columns: minmax(0, 1fr); } .preview-content { padding: 20px 14px; } .capability-grid { grid-template-columns: 1fr; gap: 28px; } .pricing { padding: 25px; } .plan { flex-direction: column; gap: 3px; } footer { flex-direction: column; } }
    </style>
</head>
<body>
<a class="skip" href="#main-content">Skip to content</a>
<header class="header wrap">
    <a class="brand" href="{{ url('/') }}" aria-label="Unified Digital Workspace home">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span>Unified Digital<br>Workspace</span>
    </a>
    <nav class="nav" aria-label="Main navigation">
        <a href="#workspace">The workspace</a>
        <a href="#capabilities">What you can do</a>
        <a href="{{ $pricingUrl }}">Pricing</a>
    </nav>
    <div class="account">
        @if ($hasLogin)<a href="{{ route('login') }}">Log in</a>@endif
        @if ($hasRegistration)<a class="button" href="{{ route('register') }}">Create account</a>@elseif($hasEnquiry)<a class="button" href="{{ route('product.enterprise') }}">Plan your workspace</a>@endif
    </div>
</header>
<main id="main-content" tabindex="-1">
    <section class="hero wrap" id="workspace" aria-labelledby="hero-title">
        <div class="hero-copy">
            <div class="intro-note">A place for your everyday work</div>
            <h1 id="hero-title">Make room for the work that matters.</h1>
            <p>Projects to move forward. Documents to get right. People to support. Bring your organisation’s daily work into one shared workspace.</p>
            <div class="actions">
                @if ($hasRegistration)
                    <a class="button" href="{{ route('register') }}">Create your account</a>
                @elseif ($hasEnquiry)
                    <a class="button" href="{{ route('product.enterprise') }}">Plan your workspace</a>
                @elseif ($hasLogin)
                    <a class="button" href="{{ route('login') }}">Log in to your workspace</a>
                @endif
                <a class="button secondary" href="#capabilities">Explore the workspace</a>
            </div>
            <p class="availability">Choose the modules and access that fit your organisation.</p>
        </div>
        <figure class="workbench" aria-labelledby="preview-caption">
            <div class="window">
                <div class="window-bar"><span class="window-dots" aria-hidden="true"><i></i><i></i><i></i></span><span>Example organisation / Workspace</span></div>
                <div class="window-body">
                    <aside class="preview-sidebar" aria-label="Illustrative workspace areas">
                        <strong>Your workspace</strong>
                        <ul><li class="selected">Overview</li><li>Projects</li><li>Documents</li><li>People</li><li>Forms</li><li>Sites</li></ul>
                    </aside>
                    <div class="preview-content">
                        <h2>A clearer working day.</h2>
                        <p class="preview-subtitle">The next step, with the context beside it.</p>
                        <div class="queue-heading"><strong>Work in focus</strong><span>Sample queue</span></div>
                        <ul class="queue">
                            <li><span class="queue-icon" aria-hidden="true">✓</span><span>Prepare the launch plan<small>Projects / Website launch</small></span><span class="status">In progress</span></li>
                            <li><span class="queue-icon" aria-hidden="true">≡</span><span>Review the staff handbook<small>Documents / People &amp; culture</small></span><span class="status">For review</span></li>
                            <li><span class="queue-icon" aria-hidden="true">↗</span><span>Publish the team update<small>Sites / Newsroom</small></span><span class="status ready">Ready</span></li>
                        </ul>
                        <div class="preview-bottom">
                            <div class="preview-tile"><strong>Documents</strong><p>Staff handbook<br>Version 2 · Shared with team</p></div>
                            <div class="preview-tile"><strong>People</strong><p>Leave request<br>Awaiting approval</p></div>
                        </div>
                    </div>
                </div>
            </div>
            <figcaption id="preview-caption">Illustrative workspace · Sample content, not a live account</figcaption>
        </figure>
    </section>
    <div class="tool-strip wrap">
        <span>Different kinds of work. One workspace.</span>
        <ul aria-label="Workspace tools"><li>Projects</li><li>Documents</li><li>People</li><li>Forms</li><li>Sites</li></ul>
    </div>
    <section class="capabilities wrap" id="capabilities" aria-labelledby="capabilities-title">
        <div class="section-intro">
            <h2 id="capabilities-title">From the first request<br>to the finished work.</h2>
            <p>Give your team a place to organise the work, keep its context and share the result. Add the modules your organisation needs.</p>
        </div>
        <div class="capability-grid">
            <article class="capability"><h3>Move work forward.</h3><p>Organise projects and tasks, track milestones and time, and collect requests through forms and surveys.</p><p class="tools">Projects &amp; tasks / Forms &amp; surveys</p></article>
            <article class="capability"><h3>Keep the knowledge.</h3><p>Manage document versions, share files with the right people, and give internal updates a home on your intranet.</p><p class="tools">Documents / Intranet</p></article>
            <article class="capability"><h3>Support your people.</h3><p>Maintain employee records, organise departments, and manage leave requests and approvals.</p><p class="tools">Employee records / Leave management</p></article>
            <article class="capability"><h3>Put your work out there.</h3><p>Create and manage a public website with posts, media and forms, alongside your organisation’s internal tools.</p><p class="tools">Sites &amp; publishing / Media library</p></article>
        </div>
    </section>
    <section class="pricing wrap" aria-labelledby="pricing-title">
        <div>
            <h2 id="pricing-title">A website today.<br>A wider workspace when you need it.</h2>
            <p>Explore website plans, then see how member access, business modules and usage shape your workspace estimate.</p>
            <a class="button" href="{{ $pricingUrl }}">See plans &amp; estimate costs</a>
        </div>
        <div>
            @foreach ($websitePlans as $plan)
                <div class="plan"><span>{{ $plan['name'] }}</span><strong>GHS {{ number_format($plan['GHS'], 0) }} <small>/ month</small></strong></div>
            @endforeach
            <p class="price-note">Opt-in website plans. Setup, additional access and usage charges may apply. Business modules are priced separately. Existing agreements keep their agreed rates.</p>
        </div>
    </section>
    <section class="closing wrap" aria-labelledby="closing-title">
        <div><h2 id="closing-title">Give good work a place to happen.</h2><p>Start with the tools your team needs. Keep the work connected.</p></div>
        <div class="actions">
            @if ($hasRegistration)<a class="button" href="{{ route('register') }}">Create your account</a>@endif
            @if ($hasLogin)<a class="button secondary" href="{{ route('login') }}">Log in</a>@endif
        </div>
    </section>
</main>
<footer class="wrap">
    <span>© {{ date('Y') }} Unified Digital Workspace</span>
    <nav aria-label="Footer navigation"><a href="#capabilities">Workspace tools</a><a href="{{ $pricingUrl }}">Plans &amp; pricing</a></nav>
</footer>
</body>
</html>
