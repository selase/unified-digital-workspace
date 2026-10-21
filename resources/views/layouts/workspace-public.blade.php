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
    <meta name="description" content="Unified Digital Workspace — Every tool for your everyday work in one shared workspace. Projects, documents, memos, people, forms, and public websites.">
    <meta name="theme-color" content="#2455b5">
    <title>@yield('title', 'Unified Digital Workspace — Work, Tools, One Workspace')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/media/logos/udw-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @if ($workspaceCalculator ?? false)
        @vite(['resources/css/app.css'])
        @livewireStyles
    @endif
    @stack('styles')
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
        html { scroll-behavior: smooth; scroll-padding-top: 88px; }
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

        /* Exposed Architectural Grid System (Laravel-inspired structural precision) */
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

        /* Crosshair Grid Markers */
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

        /* Top Iconic Blue Accent Bar */
        .iconic-top-bar {
            height: 4px;
            background: linear-gradient(90deg, var(--navy) 0%, var(--blue) 50%, var(--blue-bright) 100%);
        }

        /* Header */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(255, 255, 255, 0.92);
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
        .nav-cell a:hover {
            color: var(--blue);
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
        .link-login:hover {
            color: var(--blue);
        }

        /* Buttons */
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
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
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
        .btn-white {
            background: var(--white);
            color: var(--navy);
            border-color: var(--white);
        }
        .btn-white:hover {
            background: var(--paper);
            border-color: var(--paper);
            color: var(--blue);
        }

        /* Section Index Label (Architectural Kicker, No Pills) */
        .index-kicker {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 500;
            color: var(--blue);
            letter-spacing: 0.04em;
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

        /* HERO — Strictly Left-Aligned Architectural Split */
        .hero-top {
            display: grid;
            grid-template-columns: 1.25fr 0.75fr;
            border-bottom: 1px solid var(--line);
        }
        .hero-main {
            padding: 72px 56px 64px 48px;
            border-right: 1px solid var(--line);
        }
        .hero-main h1 {
            font-size: clamp(44px, 5.4vw, 76px);
            font-weight: 700;
            line-height: 1.02;
            letter-spacing: -0.045em;
            color: var(--ink);
            max-width: 760px;
        }
        .hero-main h1 .blue-accent {
            color: var(--blue);
            display: block;
        }
        .hero-lead {
            margin-top: 28px;
            font-size: clamp(17px, 1.5vw, 20px);
            line-height: 1.6;
            color: var(--muted);
            max-width: 600px;
        }
        .hero-actions {
            margin-top: 36px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        /* Right Hero Column — Architectural Manifesto & Tool Directory */
        .hero-aside {
            background: var(--paper-warm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hero-aside-top {
            padding: 48px 40px;
            border-bottom: 1px solid var(--line);
        }
        .hero-aside-label {
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 18px;
        }
        .tool-index-list {
            display: grid;
            gap: 0;
            border-top: 1px solid var(--line);
        }
        .tool-index-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 0;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }
        .tool-index-item span.code {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 400;
            color: var(--blue);
        }
        .tool-index-item span.desc {
            font-weight: 400;
            color: var(--muted);
            font-size: 13px;
        }

        .hero-aside-bottom {
            padding: 32px 40px;
            background: var(--blue);
            color: var(--white);
        }
        .hero-aside-bottom .stat-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
        }
        .hero-aside-bottom strong {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.03em;
        }
        .hero-aside-bottom p {
            margin-top: 6px;
            font-size: 13px;
            color: #dbe8ff;
            line-height: 1.5;
        }

        /* THE ICONIC BLUE WORKBENCH STAGE */
        .workbench-stage {
            background: var(--blue);
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.07) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.07) 1px, transparent 1px),
                linear-gradient(180deg, var(--navy) 0%, var(--blue) 100%);
            background-size: 40px 40px, 40px 40px, 100% 100%;
            padding: 48px;
        }

        .stage-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
            color: var(--white);
        }
        .stage-header-left h2 {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.02em;
        }
        .stage-header-left p {
            font-size: 13px;
            color: #cfe0ff;
            margin-top: 4px;
        }

        /* Interactive Tool Switcher Bar */
        .stage-tabs {
            display: flex;
            min-width: 0;
            max-width: 100%;
            gap: 2px;
            background: rgba(15, 34, 74, 0.55);
            padding: 4px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            overflow-x: auto;
        }
        .stage-tab {
            flex-shrink: 0;
            padding: 8px 14px;
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 600;
            color: #cfe0ff;
            background: transparent;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            white-space: nowrap;
            transition: all .15s ease;
        }
        .stage-tab:hover {
            color: var(--white);
        }
        .stage-tab[aria-selected="true"] {
            background: var(--white);
            color: var(--navy);
            box-shadow: 0 2px 8px rgba(10, 25, 54, 0.2);
        }

        /* Application Window Frame */
        .app-window {
            background: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 28px 70px rgba(11, 25, 54, 0.38);
        }
        .app-titlebar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: var(--paper);
            border-bottom: 1px solid var(--line);
            font-size: 12px;
            color: var(--muted);
        }
        .app-titlebar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .window-controls {
            display: flex;
            gap: 6px;
        }
        .window-controls i {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #c5d2e6;
            display: block;
        }
        .app-path {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--ink);
            font-weight: 500;
        }
        .app-path span { color: var(--muted); }

        .app-body {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            min-height: 440px;
        }
        .app-sidebar {
            background: var(--paper-warm);
            border-right: 1px solid var(--line);
            padding: 20px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .app-nav-group-label {
            font-family: var(--font-mono);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            padding: 0 10px 8px;
        }
        .app-nav {
            display: grid;
            gap: 2px;
        }
        .app-nav-btn {
            width: 100%;
            text-align: left;
            padding: 9px 10px;
            border-radius: 5px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-family: var(--font-sans);
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }
        .app-nav-btn:hover {
            background: #e9f0fb;
            color: var(--ink);
        }
        .app-nav-btn.active {
            background: var(--blue);
            color: var(--white);
            font-weight: 600;
        }
        .app-nav-btn .count {
            font-family: var(--font-mono);
            font-size: 11px;
            opacity: 0.8;
        }

        .app-sidebar-footer {
            border-top: 1px solid var(--line);
            padding: 14px 10px 0;
            font-size: 11.5px;
            color: var(--muted);
        }
        .app-sidebar-footer strong {
            display: block;
            color: var(--ink);
            font-size: 12px;
            margin-bottom: 2px;
        }

        /* Workspace Interactive Views */
        .app-main {
            min-width: 0;
            padding: 28px 32px;
            background: var(--white);
        }
        .workspace-view {
            display: none;
        }
        .workspace-view.active {
            display: block;
        }

        .view-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }
        .view-header h3 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--ink);
        }
        .view-header p {
            font-size: 13.5px;
            color: var(--muted);
            margin-top: 4px;
        }
        .view-meta {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--blue);
            text-align: right;
        }

        .metrics-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid var(--line);
        }
        .metric-cell {
            padding: 18px 20px 18px 0;
            border-right: 1px solid var(--line);
        }
        .metric-cell:not(:first-child) {
            padding-left: 20px;
        }
        .metric-cell:last-child {
            border-right: none;
        }
        .metric-cell .m-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
        }
        .metric-cell .m-val {
            font-size: 26px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.03em;
            margin-top: 4px;
        }
        .metric-cell .m-sub {
            font-size: 11.5px;
            color: var(--blue);
            font-weight: 600;
            margin-top: 2px;
        }

        /* Workspace Table Rows */
        .ws-table-scroll { max-width: 100%; overflow-x: auto; }
        .ws-table-scroll:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        .table-scroll-hint { display: none; }
        .ws-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 13.5px;
        }
        .ws-table th {
            text-align: left;
            padding: 10px 12px 10px 0;
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            border-bottom: 1px solid var(--line);
        }
        .ws-table td {
            padding: 14px 12px 14px 0;
            border-bottom: 1px solid #edf2f9;
            color: var(--ink);
            vertical-align: middle;
        }
        .ws-table tr:last-child td {
            border-bottom: none;
        }
        .ws-table .row-title {
            font-weight: 600;
            color: var(--ink);
        }
        .ws-table .row-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }
        .ws-status {
            font-family: var(--font-mono);
            font-size: 11.5px;
            font-weight: 500;
            color: var(--blue);
        }
        .ws-status.done { color: var(--green); }
        .ws-status.warn { color: #9a5b13; }

        .stage-caption {
            margin-top: 14px;
            display: flex;
            justify-content: space-between;
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: #cfe0ff;
        }

        /* SECTION 02: THE TOOLS — Left-Aligned Architectural Grid */
        /* Real Section Separator / Interstitial Chapter Divider (Breaks the Continuous Rail Grid) */
        .section-separator {
            width: 100%;
            background: var(--paper-warm);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 68px 0 60px;
            position: relative;
        }
        .section-separator.sep-alt {
            background: var(--paper);
        }
        .section-separator.sep-compact {
            padding: 52px 0 48px;
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
            max-width: 820px;
        }
        .sep-heading {
            font-size: clamp(26px, 2.7vw, 38px);
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
            max-width: 720px;
        }

        /* Tools specification top bar */
        .tools-spec-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 48px;
            background: var(--paper-warm);
            border-bottom: 1px solid var(--line);
            font-size: 13.5px;
        }
        .tools-spec-left strong {
            color: var(--ink);
            font-weight: 600;
            margin-right: 12px;
        }
        .tools-spec-left span {
            color: var(--muted);
        }
        .tools-spec-right {
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--blue);
            font-weight: 500;
            white-space: nowrap;
        }

        /* Ledger highlights list */
        .ledger-highlights {
            margin-top: 24px;
            display: grid;
            gap: 16px;
        }
        .ledger-highlights li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 13.5px;
            line-height: 1.45;
        }
        .lh-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: rgba(36, 85, 181, 0.12);
            color: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .ledger-highlights strong {
            display: block;
            color: var(--ink);
            font-size: 14px;
        }
        .ledger-highlights span {
            color: var(--muted);
        }

        .section-head-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--line);
        }
        .section-head-left {
            padding: 64px 48px;
            border-right: 1px solid var(--line);
        }
        .section-head-left h2 {
            font-size: clamp(32px, 3.6vw, 48px);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -0.035em;
            color: var(--ink);
        }
        .section-head-right {
            padding: 64px 48px;
            display: flex;
            align-items: flex-end;
            background: var(--paper-warm);
        }
        .section-head-right p {
            font-size: 17px;
            line-height: 1.65;
            color: var(--muted);
            max-width: 520px;
        }

        .tools-matrix {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
        }
        .tool-cell {
            padding: 44px 40px;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--white);
            transition: background .15s ease;
        }
        .tool-cell:nth-child(3n) {
            border-right: none;
        }
        .tool-cell:nth-last-child(-n+3) {
            border-bottom: none;
        }
        .tool-cell:hover {
            background: var(--paper-warm);
        }
        .tool-num {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--blue);
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .tool-num::after {
            content: "";
            width: 8px;
            height: 8px;
            background: var(--blue);
            display: inline-block;
        }
        .tool-cell h3 {
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--ink);
            margin-bottom: 12px;
        }
        .tool-cell p {
            font-size: 14.5px;
            line-height: 1.65;
            color: var(--muted);
            margin-bottom: 28px;
        }
        .tool-spec {
            padding-top: 16px;
            border-top: 1px solid var(--line);
            font-family: var(--font-mono);
            font-size: 11.5px;
            color: var(--navy);
            font-weight: 500;
        }

        /* SECTION 03: WORK vs FRAGMENTATION — Left-Aligned Asymmetrical Ledger */
        .ledger-grid {
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
        }
        .ledger-left {
            padding: 64px 48px;
            border-right: 1px solid var(--line);
            background: var(--paper);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ledger-left h2 {
            font-size: clamp(30px, 3.2vw, 44px);
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: -0.035em;
            color: var(--ink);
        }
        .ledger-left p {
            margin-top: 18px;
            font-size: 16px;
            line-height: 1.65;
            color: var(--muted);
        }
        .ledger-callout {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 2px solid var(--blue);
        }
        .ledger-callout strong {
            display: block;
            font-size: 15px;
            color: var(--ink);
        }
        .ledger-callout span {
            font-size: 13.5px;
            color: var(--muted);
            display: block;
            margin-top: 4px;
        }

        .ledger-right {
            display: grid;
        }
        .ledger-row {
            display: grid;
            grid-template-columns: 160px 1fr 1fr;
            gap: 24px;
            padding: 28px 40px;
            border-bottom: 1px solid var(--line);
            align-items: start;
        }
        .ledger-row:last-child {
            border-bottom: none;
        }
        .ledger-row-header {
            background: var(--paper-warm);
            padding-block: 16px;
            font-family: var(--font-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            font-weight: 500;
        }
        .ledger-dim {
            font-weight: 700;
            font-size: 14px;
            color: var(--ink);
        }
        .ledger-old {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.55;
        }
        .ledger-new {
            font-size: 14px;
            color: var(--ink);
            font-weight: 500;
            line-height: 1.55;
            border-left: 2px solid var(--blue);
            padding-left: 16px;
        }

        /* SECTION 04: ICONIC BLUE STATEMENT — Pricing & Architecture */
        .iconic-blue-band {
            background: var(--blue);
            color: var(--white);
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .iconic-blue-left {
            padding: 68px 48px;
            border-right: 1px solid rgba(255, 255, 255, 0.18);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .iconic-blue-left .index-kicker {
            color: #cfe0ff;
        }
        .iconic-blue-left .index-kicker::before {
            background: var(--white);
        }
        .iconic-blue-left h2 {
            font-size: clamp(32px, 3.5vw, 48px);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -0.035em;
            color: var(--white);
            max-width: 480px;
        }
        .iconic-blue-left p {
            margin-top: 20px;
            font-size: 16px;
            line-height: 1.65;
            color: #dbe8ff;
            max-width: 460px;
        }
        .iconic-blue-actions {
            margin-top: 36px;
        }

        .iconic-blue-right {
            padding: 68px 48px;
            background: var(--navy);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .plan-ledger {
            border-top: 1px solid rgba(255, 255, 255, 0.18);
        }
        .plan-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 24px;
            padding: 24px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        }
        .plan-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--white);
        }
        .plan-sub {
            font-size: 13px;
            color: #b8cef5;
            margin-top: 4px;
        }
        .plan-price {
            font-size: 30px;
            font-weight: 700;
            letter-spacing: -0.035em;
            color: var(--white);
            white-space: nowrap;
        }
        .plan-price small {
            font-size: 13px;
            font-weight: 400;
            color: #b8cef5;
            letter-spacing: normal;
        }
        .plan-footnote {
            margin-top: 24px;
            font-size: 12.5px;
            line-height: 1.65;
            color: #b8cef5;
        }

        /* CLOSING — Left-Aligned Action Bar */
        .closing-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
        }
        .closing-left {
            padding: 64px 48px;
            border-right: 1px solid var(--line);
        }
        .closing-left h2 {
            font-size: clamp(30px, 3.2vw, 44px);
            font-weight: 700;
            letter-spacing: -0.035em;
            color: var(--ink);
        }
        .closing-left p {
            margin-top: 10px;
            font-size: 16px;
            color: var(--muted);
        }
        .closing-right {
            padding: 64px 48px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            background: var(--paper-warm);
            height: 100%;
            align-items: center;
        }

        /* FOOTER */
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
        .footer-links a:hover {
            color: var(--blue);
        }
        .footer-copy {
            padding: 24px 28px;
            border-left: 1px solid var(--line);
            font-family: var(--font-mono);
            font-size: 11.5px;
        }

        /* Responsive */
        @media (max-width: 1080px) {
            .header-grid { grid-template-columns: minmax(0, 1fr) auto; }
            .nav-cell { display: none; }
            .separator-inner {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .tools-spec-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .hero-top,
            .section-head-grid,
            .ledger-grid,
            .iconic-blue-band,
            .closing-grid {
                grid-template-columns: minmax(0, 1fr);
            }
            .hero-main,
            .section-head-left,
            .ledger-left,
            .iconic-blue-left,
            .closing-left {
                border-right: none;
                border-bottom: 1px solid var(--line);
            }
            .iconic-blue-left {
                border-bottom-color: rgba(255, 255, 255, 0.18);
            }
            .tools-matrix {
                grid-template-columns: repeat(2, 1fr);
            }
            .tool-cell:nth-child(3n) { border-right: 1px solid var(--line); }
            .tool-cell:nth-child(2n) { border-right: none; }
            .app-body { grid-template-columns: minmax(0, 1fr); }
            .app-sidebar { display: none; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-brand, .footer-copy { border: none; border-bottom: 1px solid var(--line); }
        }

        @media (max-width: 720px) {
            html { scroll-padding-top: 152px; }
            .rail { width: calc(100% - 24px); }
            .section-separator { padding: 44px 0 40px; }
            .separator-inner { padding-inline: 24px; gap: 14px; }
            .tools-spec-bar { padding: 16px 24px; }
            .nav-cell { display: none; }
            .header-grid { grid-template-columns: minmax(0, 1fr); }
            .brand-cell { padding: 14px 24px; border-right: none; border-bottom: 1px solid var(--line); }
            .brand-mark { flex-shrink: 0; }
            .account-cell { flex-wrap: wrap; padding: 12px 24px; border-left: none; }
            .app-main { padding: 20px 16px; }
            .view-header { flex-wrap: wrap; }
            .view-meta { text-align: left; }
            .app-titlebar, .stage-caption, .plan-row { flex-wrap: wrap; gap: 12px; }
            .ws-table { min-width: 520px; }
            .table-scroll-hint { display: block; margin-top: 16px; font-size: 12px; color: var(--muted); }
            .app-path { overflow-wrap: anywhere; }
            .hero-main,
            .hero-aside-top,
            .hero-aside-bottom,
            .workbench-stage,
            .section-head-left,
            .section-head-right,
            .tool-cell,
            .ledger-left,
            .iconic-blue-left,
            .iconic-blue-right,
            .closing-left,
            .closing-right {
                padding: 32px 24px;
            }
            .tools-matrix { grid-template-columns: 1fr; }
            .tool-cell { border-right: none !important; border-bottom: 1px solid var(--line) !important; }
            .metrics-strip { grid-template-columns: 1fr; gap: 16px; }
            .metric-cell { border-right: none; padding: 12px 0 !important; border-bottom: 1px solid var(--line); }
            .ledger-row { grid-template-columns: 1fr; gap: 10px; padding: 24px; }
            .ledger-row-header { display: none; }
            .stage-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<a class="skip" href="#main-content">Skip to content</a>

<div class="iconic-top-bar" aria-hidden="true"></div>

{{-- Sticky Architectural Header --}}
<header class="site-header">
    <div class="rail header-grid">
        <div class="brand-cell">
            <a class="brand" href="{{ url('/') }}" aria-label="Unified Digital Workspace home">
                <span class="brand-mark" aria-hidden="true"><x-udw-logo size="32" /></span>
                <span>Unified Digital Workspace</span>
            </a>
        </div>

        <nav class="nav-cell" aria-label="Main navigation">
            <a href="{{ ($workspaceHome ?? false) ? '#workspace' : url('/').'#workspace' }}">Workspace</a>
            <a href="{{ ($workspaceHome ?? false) ? '#tools' : url('/').'#tools' }}">Tools</a>
            <a href="{{ ($workspaceHome ?? false) ? '#why-unified' : url('/').'#why-unified' }}">Why Unified</a>
            <a href="{{ ($workspaceHome ?? false) ? '#pricing' : $pricingUrl }}">Pricing</a>
            @if (\Illuminate\Support\Facades\Route::has('product.modules'))
                <a href="{{ route('product.modules') }}">Modules</a>
            @elseif (\Illuminate\Support\Facades\Route::has('product.docs'))
                <a href="{{ route('product.docs') }}">Modules</a>
            @endif
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
    @yield('content')
</main>

<footer class="rail">
    <div class="footer-grid">
        <div class="footer-brand" style="display: inline-flex; align-items: center; gap: 10px;">
            <x-udw-logo size="20" badge-radius="5" />
            <span>Unified Digital Workspace</span>
        </div>
        <nav class="footer-links" aria-label="Footer navigation">
            <a href="{{ ($workspaceHome ?? false) ? '#workspace' : url('/').'#workspace' }}">Workspace</a>
            <a href="{{ ($workspaceHome ?? false) ? '#tools' : url('/').'#tools' }}">Tools</a>
            <a href="{{ ($workspaceHome ?? false) ? '#why-unified' : url('/').'#why-unified' }}">Why Unified</a>
            <a href="{{ $pricingUrl }}">Plans &amp; pricing</a>
            @if (\Illuminate\Support\Facades\Route::has('product.modules'))
                <a href="{{ route('product.modules') }}">Modules</a>
            @elseif (\Illuminate\Support\Facades\Route::has('product.docs'))
                <a href="{{ route('product.docs') }}">Modules</a>
            @endif
        </nav>
        <div class="footer-copy">&copy; {{ date('Y') }} Unified Digital Workspace</div>
    </div>
</footer>

@if ($workspaceCalculator ?? false)
    @livewireScripts
@endif
@stack('scripts')
</body>
</html>
