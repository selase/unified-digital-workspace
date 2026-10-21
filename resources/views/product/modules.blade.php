@php
    $pricingUrl = route('product.template').'#pricing';
    $hasRegistration = \Illuminate\Support\Facades\Route::has('register') && config('app.system_setting.allow_registration', false);
    $hasEnquiry = \Illuminate\Support\Facades\Route::has('product.enterprise');
    $hasLogin = \Illuminate\Support\Facades\Route::has('login');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore every module of the Unified Digital Workspace. Purpose-built tools for projects, documents, people, CMS, forms, CRM, facilities, and automation.">
    <meta name="theme-color" content="#2455b5">
    <title>Modules — Unified Digital Workspace</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/media/logos/udw-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --ink: #172a46;
            --muted: #52627b;
            --blue: #2455b5;
            --blue-deep: #1b4291;
            --blue-bright: #2d63ce;
            --navy: #173a75;
            --paper: #f2f6fc;
            --paper-warm: #f7f9fd;
            --line: #d7e0ed;
            --line-strong: #b9c8de;
            --green: #20634d;
            --white: #ffffff;
            --font-sans: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: var(--white);
            color: var(--ink);
            font-family: var(--font-sans);
            font-size: 16px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a { color: inherit; text-decoration: none; }
        a:focus-visible, button:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 3px;
        }
        h1, h2, h3, h4, p, figure, ul, ol { margin: 0; padding: 0; }
        ul { list-style: none; }

        .skip {
            position: absolute;
            z-index: 100;
            left: 16px;
            top: -100px;
            padding: 10px 18px;
            background: var(--blue);
            color: var(--white);
            font-weight: 600;
            font-size: 13px;
        }
        .skip:focus { top: 12px; }

        .rail {
            width: min(1320px, calc(100% - 48px));
            margin-inline: auto;
            border-inline: 1px solid var(--line);
            position: relative;
        }
        .band {
            border-bottom: 1px solid var(--line);
            position: relative;
        }

        /* Top Accent Bar */
        .iconic-top-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--navy) 0%, var(--blue) 50%, var(--blue-bright) 100%);
        }

        /* Header */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .header-grid {
            display: grid;
            grid-template-columns: 280px 1fr auto;
            align-items: stretch;
            min-height: 68px;
        }
        .brand-cell {
            display: flex;
            align-items: center;
            padding-inline: 28px;
            border-right: 1px solid var(--line);
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--ink);
        }
        .brand-mark {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            line-height: 0;
        }
        .brand-mark svg {
            display: block;
            width: 32px;
            height: 32px;
        }

        .nav-cell {
            display: flex;
            align-items: center;
            gap: 32px;
            padding-inline: 32px;
            font-size: 14px;
            font-weight: 500;
            color: var(--muted);
        }
        .nav-cell a {
            padding-block: 4px;
            transition: color .15s ease;
        }
        .nav-cell a:hover,
        .nav-cell a.active {
            color: var(--blue);
            font-weight: 600;
        }

        .account-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-inline: 28px;
            border-left: 1px solid var(--line);
            font-size: 14px;
            font-weight: 600;
        }
        .link-login {
            padding: 8px 14px;
            color: var(--ink);
        }
        .link-login:hover { color: var(--blue); }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: -0.01em;
            border-radius: 5px;
            border: 1px solid var(--blue);
            background: var(--blue);
            color: var(--white);
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
        }
        .btn:hover {
            background: var(--blue-deep);
            border-color: var(--blue-deep);
        }
        .btn-lg {
            padding: 14px 26px;
            font-size: 15px;
        }
        .btn-outline {
            background: var(--white);
            color: var(--ink);
            border-color: var(--line-strong);
        }
        .btn-outline:hover {
            background: var(--paper);
            border-color: var(--blue);
            color: var(--blue);
        }

        /* Hero */
        .modules-hero {
            display: grid;
            grid-template-columns: 1.25fr 0.75fr;
            border-bottom: 1px solid var(--line);
        }
        .hero-main {
            padding: 68px 56px 60px 48px;
            border-right: 1px solid var(--line);
        }
        .index-kicker {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 600;
            color: var(--blue);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .index-kicker::before {
            content: "";
            width: 24px;
            height: 2px;
            background: var(--blue);
            display: inline-block;
        }
        .hero-main h1 {
            font-size: clamp(38px, 4.4vw, 64px);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -0.04em;
            color: var(--ink);
        }
        .hero-main h1 .blue-accent {
            color: var(--blue);
            display: block;
        }
        .hero-lead {
            margin-top: 24px;
            font-size: 18px;
            line-height: 1.65;
            color: var(--muted);
            max-width: 660px;
        }

        .hero-aside {
            background: var(--paper-warm);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hero-aside-label {
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--muted);
            margin-bottom: 16px;
        }
        .arch-checklist {
            display: grid;
            gap: 14px;
            border-top: 1px solid var(--line);
            padding-top: 18px;
        }
        .arch-check-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13.5px;
        }
        .arch-check-item strong {
            display: block;
            color: var(--ink);
            font-weight: 600;
            font-size: 14px;
        }
        .arch-check-item span {
            color: var(--muted);
            display: block;
            font-size: 12.5px;
            margin-top: 2px;
        }
        .check-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--blue);
            margin-top: 6px;
            flex-shrink: 0;
        }
        .hero-aside-bottom {
            margin-top: 32px;
            padding: 20px;
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 6px;
            font-size: 13px;
            color: var(--muted);
        }
        .hero-aside-bottom strong {
            display: block;
            color: var(--ink);
            font-size: 13.5px;
            margin-bottom: 4px;
        }

        /* Filter Navigation Bar */
        .category-nav-bar {
            position: sticky;
            top: 69px;
            z-index: 30;
            background: var(--white);
            border-bottom: 1px solid var(--line);
            overflow-x: auto;
        }
        .category-nav-grid {
            display: flex;
            align-items: center;
            gap: 0;
            min-height: 52px;
        }
        .cat-jump {
            padding: 14px 22px;
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: var(--muted);
            border-right: 1px solid var(--line);
            white-space: nowrap;
            transition: all .15s ease;
        }
        .cat-jump:hover {
            color: var(--blue);
            background: var(--paper-warm);
        }
        .cat-jump.active {
            color: var(--blue);
            background: var(--paper);
            font-weight: 700;
        }

        /* Section Chapter Separator (Breaks continuous rail) */
        .section-separator {
            width: 100%;
            background: var(--paper-warm);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 64px 0 56px;
            position: relative;
        }
        .section-separator.sep-alt {
            background: var(--paper);
        }
        .separator-inner {
            width: min(1320px, calc(100% - 48px));
            margin-inline: auto;
            padding-inline: 48px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 52px;
            align-items: flex-start;
        }
        .separator-meta {
            border-left: 3px solid var(--blue);
            padding-left: 20px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .sep-num {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: var(--blue);
            text-transform: uppercase;
        }
        .sep-label {
            font-size: 13.5px;
            color: var(--muted);
            font-weight: 500;
        }
        .separator-body {
            max-width: 840px;
        }
        .sep-heading {
            font-size: clamp(26px, 2.7vw, 36px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.035em;
            color: var(--ink);
        }
        .sep-text {
            margin-top: 12px;
            font-size: 16.5px;
            line-height: 1.65;
            color: var(--muted);
            max-width: 740px;
        }

        /* Modules Grid Matrix */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
        }
        .module-card {
            padding: 44px 40px;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--white);
            transition: background .15s ease;
        }
        .module-card:nth-child(2n) {
            border-right: none;
        }
        .module-card:hover {
            background: #fafcff;
        }
        .module-card-top {
            margin-bottom: 24px;
        }
        .module-header-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .module-id {
            font-family: var(--font-mono);
            font-size: 11.5px;
            font-weight: 600;
            color: var(--blue);
            letter-spacing: 0.06em;
        }
        .module-tier {
            font-family: var(--font-mono);
            font-size: 11px;
            color: var(--muted);
            background: var(--paper);
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid var(--line);
        }
        .module-card h3 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--ink);
            margin-bottom: 10px;
        }
        .module-desc {
            font-size: 15px;
            line-height: 1.65;
            color: var(--muted);
            margin-bottom: 22px;
        }

        .module-features-title {
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--navy);
            margin-bottom: 10px;
            font-weight: 600;
        }
        .module-features-list {
            display: grid;
            gap: 8px;
            margin-bottom: 24px;
        }
        .module-features-list li {
            position: relative;
            padding-left: 18px;
            font-size: 13.5px;
            line-height: 1.5;
            color: var(--ink);
        }
        .module-features-list li::before {
            content: "—";
            position: absolute;
            left: 0;
            color: var(--blue);
            font-weight: 600;
        }

        .module-footer-row {
            padding-top: 18px;
            border-top: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            font-size: 12px;
            color: var(--muted);
        }
        .module-footer-row strong {
            color: var(--navy);
            font-family: var(--font-mono);
            font-size: 11.5px;
        }

        /* Closing Callout */
        .modules-closing {
            background: var(--blue);
            color: var(--white);
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
        }
        .closing-left {
            padding: 68px 48px;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        .closing-left h2 {
            font-size: clamp(30px, 3.4vw, 44px);
            font-weight: 700;
            letter-spacing: -0.035em;
            color: var(--white);
            line-height: 1.1;
        }
        .closing-left p {
            margin-top: 14px;
            font-size: 16px;
            color: #dbe8ff;
            line-height: 1.6;
        }
        .closing-right {
            padding: 68px 48px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: var(--navy);
            height: 100%;
            justify-content: center;
        }
        .btn-white {
            background: var(--white);
            color: var(--navy);
            border-color: var(--white);
        }
        .btn-white:hover {
            background: var(--paper);
            color: var(--blue);
        }

        /* Footer */
        .footer-grid {
            display: grid;
            grid-template-columns: 280px 1fr auto;
            align-items: center;
            font-size: 13px;
            color: var(--muted);
        }
        .footer-brand {
            padding: 24px 28px;
            border-right: 1px solid var(--line);
            font-weight: 600;
            color: var(--ink);
        }
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 28px;
            padding: 24px 32px;
        }
        .footer-links a:hover { color: var(--blue); }
        .footer-copy {
            padding: 24px 28px;
            border-left: 1px solid var(--line);
            font-family: var(--font-mono);
            font-size: 11.5px;
        }

        /* Crosshairs */
        .crosshair-tl::before,
        .crosshair-tr::after {
            content: "+";
            position: absolute;
            top: -9px;
            font-family: var(--font-mono);
            font-size: 13px;
            line-height: 1;
            color: var(--blue);
            font-weight: 400;
            pointer-events: none;
            z-index: 5;
        }
        .crosshair-tl::before { left: -5px; }
        .crosshair-tr::after { right: -5px; }

        @media (max-width: 1080px) {
            .header-grid { grid-template-columns: auto 1fr auto; }
            .modules-hero,
            .separator-inner,
            .modules-grid,
            .modules-closing {
                grid-template-columns: 1fr;
            }
            .hero-main,
            .closing-left {
                border-right: none;
                border-bottom: 1px solid var(--line);
            }
            .closing-left {
                border-bottom-color: rgba(255, 255, 255, 0.2);
            }
            .module-card {
                border-right: none;
            }
            .separator-inner { gap: 20px; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-brand, .footer-copy { border: none; border-bottom: 1px solid var(--line); }
        }

        @media (max-width: 720px) {
            .rail { width: calc(100% - 24px); }
            .nav-cell { display: none; }
            .header-grid { grid-template-columns: 1fr auto; }
            .hero-main,
            .hero-aside,
            .module-card,
            .closing-left,
            .closing-right {
                padding: 32px 24px;
            }
            .section-separator { padding: 44px 0 40px; }
            .separator-inner { padding-inline: 24px; gap: 14px; }
        }
    </style>
</head>
<body>
<a class="skip" href="#main-content">Skip to content</a>

<div class="iconic-top-bar" aria-hidden="true"></div>

{{-- Sticky Header --}}
<header class="site-header">
    <div class="rail header-grid">
        <div class="brand-cell">
            <a class="brand" href="{{ url('/') }}" aria-label="Unified Digital Workspace home">
                <span class="brand-mark" aria-hidden="true"><x-udw-logo size="32" /></span>
                <span>Unified Digital Workspace</span>
            </a>
        </div>

        <nav class="nav-cell" aria-label="Main navigation">
            <a href="{{ url('/#workspace') }}">Workspace</a>
            <a href="{{ url('/#tools') }}">Tools</a>
            <a href="{{ url('/#why-unified') }}">Why Unified</a>
            <a href="{{ url('/#pricing') }}">Pricing</a>
            <a href="{{ route('product.modules') }}" class="active">Modules</a>
        </nav>

        <div class="account-cell">
            @if ($hasLogin)
                <a class="link-login" href="{{ route('login') }}">Log in</a>
            @endif
            @if ($hasRegistration)
                <a class="btn" href="{{ route('register') }}">Create account</a>
            @elseif ($hasEnquiry)
                <a class="btn" href="{{ route('product.enterprise') }}">Plan your workspace</a>
            @endif
        </div>
    </div>
</header>

<main id="main-content" tabindex="-1">
    {{-- Modules Hero --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="modules-title">
        <div class="modules-hero">
            <div class="hero-main">
                <div class="index-kicker">Unified Digital Workspace / Platform Modules</div>
                <h1 id="modules-title">
                    Every module.
                    <span class="blue-accent">Built to connect.</span>
                </h1>
                <p class="hero-lead">
                    Unified Digital Workspace is modular by design. Turn on the operational tools your organisation needs today, knowing every module reads and writes to one shared directory, permission tree, and audit log.
                </p>
            </div>

            <aside class="hero-aside" aria-label="Architecture summary">
                <div>
                    <div class="hero-aside-label">Architecture Guarantees</div>
                    <ul class="arch-checklist">
                        <li class="arch-check-item">
                            <span class="check-dot"></span>
                            <div>
                                <strong>100% Tenant Isolation</strong>
                                <span>Strict organization-scoped database boundaries with optional dedicated instances.</span>
                            </div>
                        </li>
                        <li class="arch-check-item">
                            <span class="check-dot"></span>
                            <div>
                                <strong>Single Directory &amp; RBAC</strong>
                                <span>One login, uniform role inheritance, and no duplicate user profiles.</span>
                            </div>
                        </li>
                        <li class="arch-check-item">
                            <span class="check-dot"></span>
                            <div>
                                <strong>Centralized Audit Trail</strong>
                                <span>Every action, sign-off, approval, and version change durably logged.</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="hero-aside-bottom">
                    <strong>Modular Deployment</strong>
                    Enable only the tools your team requires. Pay only for active usage and licenced capacity.
                </div>
            </aside>
        </div>

        {{-- Category Anchor Nav --}}
        <div class="category-nav-bar" aria-label="Module categories">
            <div class="category-nav-grid">
                <a class="cat-jump" href="#operations">01 / Operations &amp; Workflows</a>
                <a class="cat-jump" href="#knowledge">02 / Documents &amp; Governance</a>
                <a class="cat-jump" href="#people">03 / People &amp; HRMS</a>
                <a class="cat-jump" href="#web">04 / Public Web &amp; CRM</a>
                <a class="cat-jump" href="#commerce">05 / Commerce &amp; Automation</a>
            </div>
        </div>
    </section>

    {{-- CHAPTER 01: OPERATIONS & WORKFLOWS --}}
    <div class="section-separator" id="operations" aria-label="Operations chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">01 / Operations</span>
                <span class="sep-label">Workflows &amp; Assets</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Coordinate deliverables, maintain assets, and resolve incidents.</h2>
                <p class="sep-text">
                    Keep operational work structured and visible. From multi-stage project milestones and recorded timesheets to facility maintenance and incident root-cause investigations.
                </p>
            </div>
        </div>
    </div>

    <section class="rail band crosshair-tl crosshair-tr" aria-label="Operations modules">
        <div class="modules-grid">
            {{-- Module: Projects --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-01 / PROJECTS</span>
                        <span class="module-tier">Core Add-On</span>
                    </div>
                    <h3>Projects &amp; Tasks</h3>
                    <p class="module-desc">Stage-gate project management with task assignments, milestone delivery, and logged time entries.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Stage-gate workflows with milestone progress tracking</li>
                        <li>Task boards with ownership, stage transitions, and priorities</li>
                        <li>Timesheets and hour logging attached to specific workstreams</li>
                        <li>Budget tracking with actual vs. planned cost variance</li>
                        <li>5x5 Likelihood x Impact risk matrix and register</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Links to CRM clients &middot; Intake forms &middot; Staff directory</span>
                </div>
            </article>

            {{-- Module: Facilities & Assets --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-02 / FACILITIES</span>
                        <span class="module-tier">Operations Module</span>
                    </div>
                    <h3>Facilities &amp; Assets</h3>
                    <p class="module-desc">Dual-mode physical asset management, building breakdown, and preventive maintenance work orders.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Institutional asset register with serial numbers and locations</li>
                        <li>Building, floor, and space hierarchical breakdown</li>
                        <li>Preventive maintenance schedules and automated recurring tasks</li>
                        <li>Work order dispatching, status tracking, and completion sign-offs</li>
                        <li>Estate occupant service requests routed to maintenance teams</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Syncs with Vendor Marketplace &middot; Incident management</span>
                </div>
            </article>

            {{-- Module: Incidents --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-03 / INCIDENTS</span>
                        <span class="module-tier">Governance Module</span>
                    </div>
                    <h3>Incident Management &amp; CAPA</h3>
                    <p class="module-desc">Classify operational incidents, investigate root causes, and track corrective action tasks to closure.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Structured incident reporting with severity and impact scoring</li>
                        <li>Handler assignment, escalation paths, and department tagging</li>
                        <li>Corrective and Preventive Action (CAPA) task boards</li>
                        <li>Investigation commentary and root-cause analysis documentation</li>
                        <li>Compliance reporting and immutable resolution audit logs</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Tied to Facilities assets &middot; Custom report builder</span>
                </div>
            </article>

            {{-- Module: Quality Monitoring --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-04 / QUALITY</span>
                        <span class="module-tier">Institutional Module</span>
                    </div>
                    <h3>Quality Monitoring &amp; Evidence Vault</h3>
                    <p class="module-desc">Track organizational workplans, operational KPI indicators, variance alerts, and compliance evidence.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Workplan authoring, submission, and supervisory approval cycles</li>
                        <li>Operational KPI monitoring with target vs. actual telemetry</li>
                        <li>Variance logging with threshold alerts and corrective workflows</li>
                        <li>Secure Evidence Vault on private storage with access logging</li>
                        <li>Institutional audit trails for accreditation reviews</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Executive command dashboard &middot; Document vault</span>
                </div>
            </article>
        </div>
    </section>

    {{-- CHAPTER 02: DOCUMENTS & GOVERNANCE --}}
    <div class="section-separator sep-alt" id="knowledge" aria-label="Governance chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">02 / Governance</span>
                <span class="sep-label">Documents &amp; Memos</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Control policy versions, circulate memos, and keep teams informed.</h2>
                <p class="sep-text">
                    Replace lost attachments and fragmented email threads with controlled document lifecycles, confidential executive correspondence, and internal intranet broadcasts.
                </p>
            </div>
        </div>
    </div>

    <section class="rail band crosshair-tl crosshair-tr" aria-label="Knowledge modules">
        <div class="modules-grid">
            {{-- Module: Document Management --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-05 / DOCUMENTS</span>
                        <span class="module-tier">Core Module</span>
                    </div>
                    <h3>Document Management</h3>
                    <p class="module-desc">Controlled lifecycle for organizational policies, SOPs, and manuals with revision histories.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Version-controlled drafts, reviews, and formal publishing workflow</li>
                        <li>Departmental and organizational audience access restrictions</li>
                        <li>Mandatory staff reading acknowledgements with verification tracking</li>
                        <li>Attached comprehension quizzes to confirm policy understanding</li>
                        <li>Audit log of all downloads, revisions, and status transitions</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Surfaces on Intranet &middot; Staff onboarding dossiers</span>
                </div>
            </article>

            {{-- Module: Memos --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-06 / MEMOS</span>
                        <span class="module-tier">e-Office Module</span>
                    </div>
                    <h3>Institutional Memos &amp; e-Signatures</h3>
                    <p class="module-desc">Circulate official correspondence, capture minutes, and record verified digital approvals.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Formal memorandum creation with rich text and file attachments</li>
                        <li>Strict To, CC, and BCC isolation with verified confidentiality</li>
                        <li>Recipient delivery logging and individual acknowledgement status</li>
                        <li>Recorded minutes and executive annotation threads</li>
                        <li>Digital application signatures attached to approval milestones</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Org hierarchy &middot; Notification center &middot; Audit trail</span>
                </div>
            </article>

            {{-- Module: Intranet --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-07 / INTRANET</span>
                        <span class="module-tier">Communications</span>
                    </div>
                    <h3>Institutional Intranet Portal</h3>
                    <p class="module-desc">Centralized homepage for organization-wide news, departmental boards, and company events.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Internal announcements feed with audience and department scoping</li>
                        <li>Message boards with pinned notices and threaded replies</li>
                        <li>Company events calendar with attending / tentative / declined RSVPs</li>
                        <li>Curated policy and document shortcuts for quick staff reference</li>
                        <li>Strict guest leak protection preventing public exposure</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Feeds from CMS Core &middot; Document Management</span>
                </div>
            </article>

            {{-- Module: Forums --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-08 / FORUMS</span>
                        <span class="module-tier">Collaboration</span>
                    </div>
                    <h3>Forums &amp; Direct Messaging</h3>
                    <p class="module-desc">Topic channels, team discussions, moderation queues, and private 1-on-1 messages.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Public and private discussion channels with role-based access</li>
                        <li>Threaded conversations with rich text and attachment support</li>
                        <li>Moderation queue for flagging, locking, and pinning discussions</li>
                        <li>Private 1-on-1 and group direct messaging center</li>
                        <li>Integrated notifications for thread replies and direct mentions</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Workspace member directory &middot; Notification inbox</span>
                </div>
            </article>
        </div>
    </section>

    {{-- CHAPTER 03: PEOPLE & HRMS --}}
    <div class="section-separator" id="people" aria-label="People chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">03 / People &amp; HR</span>
                <span class="sep-label">Workforce Lifecycle</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Structure departments, support staff, and manage employee lifecycles.</h2>
                <p class="sep-text">
                    Maintain staff dossiers, manage decimal leave balances with holiday calendars, run recruitment pipelines, and conduct structured performance reviews.
                </p>
            </div>
        </div>
    </div>

    <section class="rail band crosshair-tl crosshair-tr" aria-label="People modules">
        <div class="modules-grid">
            {{-- Module: HRMS Dossiers & Leave --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-09 / HRMS</span>
                        <span class="module-tier">HRMS Core</span>
                    </div>
                    <h3>Employee Directory &amp; Leave</h3>
                    <p class="module-desc">Complete staff records, emergency contacts, qualifications, and automated leave management.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Employee dossiers with personal info, next of kin, and credentials</li>
                        <li>Directorate, Department, and Unit assignment with designated leaders</li>
                        <li>Decimal leave balance tracking with annual and study leave types</li>
                        <li>Public holiday calendars with automatic working-day calculations</li>
                        <li>Leave approval workflow with supervisor and HR sign-offs</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Shared user accounts &middot; Intranet birthdays &middot; Task assignees</span>
                </div>
            </article>

            {{-- Module: Recruitment & Appraisals --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-10 / TALENT</span>
                        <span class="module-tier">HRMS Extension</span>
                    </div>
                    <h3>Recruitment ATS &amp; Appraisals</h3>
                    <p class="module-desc">Manage job requisitions, candidate stages, onboarding checklists, and quarterly reviews.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Job requisitions and candidate applications tracking</li>
                        <li>Interview stage scheduling and formal hiring decision records</li>
                        <li>Automated onboarding task assignment upon candidate acceptance</li>
                        <li>Appraisal periods, customizable rating templates, and goal tracking</li>
                        <li>Supervisor and employee confidential review submissions</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Converts candidates to Employee dossiers &middot; RBAC roles</span>
                </div>
            </article>

            {{-- Module: Org Hierarchy --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-11 / HIERARCHY</span>
                        <span class="module-tier">Platform Baseline</span>
                    </div>
                    <h3>Organizational Hierarchy &amp; RBAC</h3>
                    <p class="module-desc">Flexible directorate-to-unit mapping with permission inheritance and access delegation.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Three-tier structure: Directorates &rarr; Departments &rarr; Units</li>
                        <li>Designated Head and Deputy roles with escalation routing</li>
                        <li>Role-based access control (RBAC) with granular module abilities</li>
                        <li>Automatic permission inheritance down the organizational tree</li>
                        <li>Audited user role assignment with time-stamped authorization records</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Enforces access across every enabled workspace module</span>
                </div>
            </article>

            {{-- Module: Compensation & Salary --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-12 / COMPENSATION</span>
                        <span class="module-tier">HRMS Module</span>
                    </div>
                    <h3>Salary Structures &amp; Promotions</h3>
                    <p class="module-desc">Maintain transparent grade levels, step progressions, and formal promotion review workflows.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Standard salary grades, band levels, and step progression tables</li>
                        <li>Allowance and deduction configurations by staff grade</li>
                        <li>Promotion submission with supervisor recommendations and HR review</li>
                        <li>Confidential read-only API hardening on all compensation records</li>
                        <li>Full change history logging on salary and title adjustments</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Tied to HRMS Employee Dossiers &middot; Executive reporting</span>
                </div>
            </article>
        </div>
    </section>

    {{-- CHAPTER 04: PUBLIC WEB & CRM --}}
    <div class="section-separator sep-alt" id="web" aria-label="Web chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">04 / Public Web</span>
                <span class="sep-label">Publishing &amp; Intake</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Run your public website, intake forms, and client CRM together.</h2>
                <p class="sep-text">
                    Eliminate external CMS and form subscriptions. Manage your public digital presence from the very workspace where your operational staff do their daily work.
                </p>
            </div>
        </div>
    </div>

    <section class="rail band crosshair-tl crosshair-tr" aria-label="Web modules">
        <div class="modules-grid">
            {{-- Module: CMS Core --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-13 / CMS</span>
                        <span class="module-tier">Website Foundation</span>
                    </div>
                    <h3>CMS &amp; Web Publishing</h3>
                    <p class="module-desc">Visual page builder, press newsroom, media asset management, and custom domain mapping.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Block-based page editor with flexible layout sections</li>
                        <li>Articles, press releases, and newsroom with category/tag taxonomy</li>
                        <li>Media asset library with automatic image compression and optimization</li>
                        <li>Custom domain connection with tenant-scoped SSL routing</li>
                        <li>Navigation menu builder supporting multi-level hierarchies</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Powers public website &middot; Intranet feeds &middot; Media library</span>
                </div>
            </article>

            {{-- Module: Forms & Surveys --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-14 / FORMS</span>
                        <span class="module-tier">Intake Module</span>
                    </div>
                    <h3>Forms, Surveys &amp; Live Polls</h3>
                    <p class="module-desc">Collect client inquiries, service requests, and staff feedback with real-time analytics.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Intuitive form builder with text, choice, rating, and file fields</li>
                        <li>Single-response anonymous polls with anti-abuse voter hash tracking</li>
                        <li>Submission inbox with individual response inspection and triage</li>
                        <li>Aggregate statistical charts, response percentages, and CSV export</li>
                        <li>Audience scoping: public internet, authenticated staff, or specific teams</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Creates CRM inquiries &middot; Projects tasks &middot; HR requests</span>
                </div>
            </article>

            {{-- Module: CRM --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-15 / CRM</span>
                        <span class="module-tier">Business Module</span>
                    </div>
                    <h3>Institutional CRM &amp; Deals</h3>
                    <p class="module-desc">Capture inbound inquiries, manage client accounts, and track opportunities across stages.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Inbound lead capture from website forms and walk-in visits</li>
                        <li>Interaction history logs: meetings, phone calls, and notes</li>
                        <li>Client account directories with multiple associated contacts</li>
                        <li>Deal pipelines with custom stages, values, and close dates</li>
                        <li>Lead qualification and conversion to full account status</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Connects to Forms intake &middot; Projects billing &middot; Invoices</span>
                </div>
            </article>

            {{-- Module: Real Estate --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-16 / PROPERTY</span>
                        <span class="module-tier">Specialized Module</span>
                    </div>
                    <h3>Real Estate &amp; Land Compliance</h3>
                    <p class="module-desc">Manage parcel boundaries, public listings, installment payment ledgers, and official receipts.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Project, parcel, and plot registry with spatial boundary guards</li>
                        <li>Public property listings with image gallery uploads</li>
                        <li>Client installment ledgers with gateway and audited offline receipts</li>
                        <li>Concurrent payment safety with overpayment refund tracking</li>
                        <li>Ghana land documentation, covenant vault, and clearance tracking</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Public CMS listings &middot; Payment gateway &middot; Vendor marketplace</span>
                </div>
            </article>
        </div>
    </section>

    {{-- CHAPTER 05: COMMERCE & AUTOMATION --}}
    <div class="section-separator" id="commerce" aria-label="Commerce chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">05 / Extensibility</span>
                <span class="sep-label">Commerce &amp; Automation</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Coordinate external vendors, build reports, and automate workflows.</h2>
                <p class="sep-text">
                    Extend your workspace beyond your internal team. Coordinate verified external contractors through escrow, generate cross-module business intelligence, and automate actions.
                </p>
            </div>
        </div>
    </div>

    <section class="rail band crosshair-tl crosshair-tr" aria-label="Commerce modules">
        <div class="modules-grid">
            {{-- Module: Vendor Marketplace --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-17 / VENDORS</span>
                        <span class="module-tier">Ecosystem Module</span>
                    </div>
                    <h3>Vendor Marketplace &amp; Escrow</h3>
                    <p class="module-desc">Onboard suppliers, agree audited rate cards, and settle jobs through gateway escrow.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Vendor profile onboarding with compliance document verification</li>
                        <li>Standard category rate cards with audited fee commission splits</li>
                        <li>Service job assignments dispatched from maintenance work orders</li>
                        <li>Gateway-funded escrow with currency validation and idempotent settlement</li>
                        <li>Formal completion sign-off and dispute resolution lifecycle</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Facilities work orders &middot; Real estate maintenance &middot; Billing</span>
                </div>
            </article>

            {{-- Module: Report Builder --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-18 / REPORTS</span>
                        <span class="module-tier">Intelligence Module</span>
                    </div>
                    <h3>Custom Report Builder &amp; Exporter</h3>
                    <p class="module-desc">Extract cross-module operational insights with custom column filters and CSV/PDF export.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Multi-source reporting across incidents, CRM, work orders, and sales</li>
                        <li>Custom column selection and date range constraints</li>
                        <li>Private definitions vs. shared team report visibility</li>
                        <li>Instant data preview with formatted numbers and currency values</li>
                        <li>One-click CSV and printable PDF document exports</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Queries all active modules &middot; Executive reporting</span>
                </div>
            </article>

            {{-- Module: Workflow Automation --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-19 / AUTOMATION</span>
                        <span class="module-tier">Engine Module</span>
                    </div>
                    <h3>Business Process Automation Engine</h3>
                    <p class="module-desc">Event-driven triggers and rule conditions to automate handoffs and notifications across modules.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Workspace event listener reacting to real operational actions</li>
                        <li>Condition builder: equals, not equals, greater/less than, contains</li>
                        <li>Automated task creation, status updates, and email notifications</li>
                        <li>Execution history log with payload inspection and delivery audit</li>
                        <li>Fail-safe execution preventing recursive event loops</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Listens to events across all workspace business modules</span>
                </div>
            </article>

            {{-- Module: Store --}}
            <article class="module-card">
                <div class="module-card-top">
                    <div class="module-header-row">
                        <span class="module-id">MOD-20 / STORE</span>
                        <span class="module-tier">Commerce Module</span>
                    </div>
                    <h3>Workspace Store &amp; Credit Top-Ups</h3>
                    <p class="module-desc">Purchase communication quotas (SMS, email, AI) and sell products through tenant storefronts.</p>

                    <div class="module-features-title">Core Capabilities</div>
                    <ul class="module-features-list">
                        <li>Platform credit top-ups for SMS, transactional email, and AI credits</li>
                        <li>Per-tenant digital product catalog with inventory management</li>
                        <li>Gateway checkout with redirect and verified payment settlement</li>
                        <li>Audited staff offline receipt confirmations with mandatory references</li>
                        <li>Transaction ledger tracking and printable payment receipts</li>
                    </ul>
                </div>
                <div class="module-footer-row">
                    <strong>Connected Context</strong>
                    <span>Replenishes workspace delivery quotas &middot; Transaction ledger</span>
                </div>
            </article>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="cta-title">
        <div class="modules-closing">
            <div class="closing-left">
                <div class="index-kicker" style="color: #cfe0ff;">Get Started</div>
                <h2 id="cta-title">Assemble the workspace your team needs today.</h2>
                <p>
                    Start with a website plan, then activate the operational modules that move your business forward—all in one place.
                </p>
            </div>
            <div class="closing-right">
                @if ($hasRegistration)
                    <a class="btn btn-lg btn-white" href="{{ route('register') }}">Create your account &rarr;</a>
                @elseif ($hasEnquiry)
                    <a class="btn btn-lg btn-white" href="{{ route('product.enterprise') }}">Plan your workspace &rarr;</a>
                @endif
                <a class="btn btn-lg btn-outline" style="border-color: rgba(255,255,255,0.4); color: white; background: transparent;" href="{{ $pricingUrl }}">View Pricing &amp; Plans</a>
            </div>
        </div>
    </section>
</main>

<footer class="rail">
    <div class="footer-grid">
        <div class="footer-brand" style="display: inline-flex; align-items: center; gap: 10px;">
            <x-udw-logo size="20" badge-radius="5" />
            <span>Unified Digital Workspace</span>
        </div>
        <nav class="footer-links" aria-label="Footer navigation">
            <a href="{{ url('/#workspace') }}">Workspace</a>
            <a href="{{ url('/#tools') }}">Tools</a>
            <a href="{{ url('/#why-unified') }}">Why Unified</a>
            <a href="{{ $pricingUrl }}">Plans &amp; pricing</a>
            <a href="{{ route('product.modules') }}">Modules</a>
        </nav>
        <div class="footer-copy">&copy; {{ date('Y') }} Unified Digital Workspace</div>
    </div>
</footer>
</body>
</html>

