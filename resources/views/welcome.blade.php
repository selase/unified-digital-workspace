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
    <title>Unified Digital Workspace — Work, Tools, One Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            background: var(--blue);
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
            padding: 7px;
            border-radius: 6px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.25);
        }
        .brand-mark i {
            background: var(--white);
            border-radius: 1.5px;
            display: block;
        }
        .brand-mark i:last-child {
            background: #9ec0ff;
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
            gap: 2px;
            background: rgba(15, 34, 74, 0.55);
            padding: 4px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            overflow-x: auto;
        }
        .stage-tab {
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
            .header-grid { grid-template-columns: auto 1fr auto; }
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
                grid-template-columns: 1fr;
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
            .app-body { grid-template-columns: 1fr; }
            .app-sidebar { display: none; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-brand, .footer-copy { border: none; border-bottom: 1px solid var(--line); }
        }

        @media (max-width: 720px) {
            .rail { width: calc(100% - 24px); }
            .section-separator { padding: 44px 0 40px; }
            .separator-inner { padding-inline: 24px; gap: 14px; }
            .tools-spec-bar { padding: 16px 24px; }
            .nav-cell { display: none; }
            .header-grid { grid-template-columns: 1fr auto; }
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
                <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                <span>Unified Digital Workspace</span>
            </a>
        </div>

        <nav class="nav-cell" aria-label="Main navigation">
            <a href="#workspace">Workspace</a>
            <a href="#tools">Tools</a>
            <a href="#why-unified">Why Unified</a>
            <a href="#pricing">Pricing</a>
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
    {{-- HERO: Left-Aligned Architectural Split --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="hero-title">
        <div class="hero-top">
            <div class="hero-main">
                <div class="index-kicker">Unified Digital Workspace</div>
                <h1 id="hero-title">
                    Every tool for your work.
                    <span class="blue-accent">One shared workspace.</span>
                </h1>
                <p class="hero-lead">
                    Projects to move forward. Documents and memos to get right. People to support. Public websites and forms to run. Bring your organisation’s everyday work into one connected place.
                </p>
                <div class="hero-actions">
                    @if ($hasRegistration)
                        <a class="btn btn-lg" href="{{ route('register') }}">Create your workspace</a>
                    @elseif ($hasEnquiry)
                        <a class="btn btn-lg" href="{{ route('product.enterprise') }}">Plan your workspace</a>
                    @elseif ($hasLogin)
                        <a class="btn btn-lg" href="{{ route('login') }}">Log in to your workspace</a>
                    @endif
                    <a class="btn btn-lg btn-outline" href="#tools">Explore the tools &rarr;</a>
                </div>
            </div>

            <aside class="hero-aside" aria-label="Workspace tool index">
                <div class="hero-aside-top">
                    <div class="hero-aside-label">Connected Workspace Tools</div>
                    <ul class="tool-index-list">
                        <li class="tool-index-item">
                            <span>Projects &amp; Tasks</span>
                            <span class="desc">Milestones &middot; Time</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Documents &amp; Memos</span>
                            <span class="desc">Versions &middot; Sign-offs</span>
                        </li>
                        <li class="tool-index-item">
                            <span>People &amp; HR</span>
                            <span class="desc">Records &middot; Leave</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Forms &amp; Surveys</span>
                            <span class="desc">Intake &middot; Routing</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Public Websites</span>
                            <span class="desc">CMS &middot; Domains</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Facilities &amp; Assets</span>
                            <span class="desc">Leases &middot; Work orders</span>
                        </li>
                    </ul>
                </div>
                <div class="hero-aside-bottom">
                    <div class="stat-row">
                        <strong>One workspace</strong>
                        <span>Tenant-isolated</span>
                    </div>
                    <p>Enable only the tools your organisation needs today, under one login and one permission model.</p>
                </div>
            </aside>
        </div>

        {{-- THE ICONIC BLUE STAGE: Interactive Live Workbench --}}
        <div class="workbench-stage" id="workspace">
            <div class="stage-header">
                <div class="stage-header-left">
                    <h2>Inside the Unified Digital Workspace</h2>
                    <p>Switch between tools below to see how daily work stays connected in one interface.</p>
                </div>

                <div class="stage-tabs" role="tablist" aria-label="Workspace tool preview">
                    <button type="button" class="stage-tab" role="tab" id="tab-overview" aria-selected="true" aria-controls="view-overview" onclick="selectWorkspaceView('overview')">Overview</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-projects" aria-selected="false" aria-controls="view-projects" onclick="selectWorkspaceView('projects')">Projects</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-documents" aria-selected="false" aria-controls="view-documents" onclick="selectWorkspaceView('documents')">Documents &amp; Memos</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-people" aria-selected="false" aria-controls="view-people" onclick="selectWorkspaceView('people')">People &amp; HR</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-sites" aria-selected="false" aria-controls="view-sites" onclick="selectWorkspaceView('sites')">Sites &amp; Forms</button>
                </div>
            </div>

            <figure class="app-window" aria-labelledby="workbench-caption">
                <div class="app-titlebar">
                    <div class="app-titlebar-left">
                        <span class="window-controls" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="app-path">Example Organisation <span>/</span> <strong id="active-path-label">Workspace Overview</strong></span>
                    </div>
                    <span>Unified Digital Workspace</span>
                </div>

                <div class="app-body">
                    <aside class="app-sidebar" aria-label="Illustrative workspace navigation">
                        <div>
                            <div class="app-nav-group-label">Your Workspace</div>
                            <div class="app-nav">
                                <button type="button" class="app-nav-btn active" data-nav="overview" onclick="selectWorkspaceView('overview')">
                                    <span>Overview</span>
                                    <span class="count">04</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="projects" onclick="selectWorkspaceView('projects')">
                                    <span>Projects &amp; Tasks</span>
                                    <span class="count">12</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="documents" onclick="selectWorkspaceView('documents')">
                                    <span>Documents &amp; Memos</span>
                                    <span class="count">38</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="people" onclick="selectWorkspaceView('people')">
                                    <span>People &amp; Leave</span>
                                    <span class="count">48</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="sites" onclick="selectWorkspaceView('sites')">
                                    <span>Sites &amp; Forms</span>
                                    <span class="count">06</span>
                                </button>
                            </div>
                        </div>

                        <div class="app-sidebar-footer">
                            <strong>Connected Context</strong>
                            Shared roles, audit history, and directory across every tool.
                        </div>
                    </aside>

                    <div class="app-main">
                        {{-- View 1: Overview --}}
                        <div class="workspace-view active" id="view-overview" role="tabpanel" aria-labelledby="tab-overview">
                            <div class="view-header">
                                <div>
                                    <h3>A clearer working day.</h3>
                                    <p>Your projects, documents, approvals, and website updates in one shared queue.</p>
                                </div>
                                <div class="view-meta">TODAY'S FOCUS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Active Projects</div>
                                    <div class="m-val">12</div>
                                    <div class="m-sub">3 milestones due this week</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Documents &amp; Memos</div>
                                    <div class="m-val">38</div>
                                    <div class="m-sub">2 awaiting sign-off</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">People &amp; Requests</div>
                                    <div class="m-val">48</div>
                                    <div class="m-sub">1 leave approval pending</div>
                                </div>
                            </div>

                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Item in Focus</th>
                                        <th>Tool</th>
                                        <th>Owner</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Prepare the Q4 Website &amp; Campaign Launch</div>
                                            <div class="row-sub">Milestone deliverable and media review</div>
                                        </td>
                                        <td>Projects</td>
                                        <td>Operations Team</td>
                                        <td><span class="ws-status">In progress</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Review Updated Staff Handbook (Version 3.2)</div>
                                            <div class="row-sub">Controlled document shared with department leads</div>
                                        </td>
                                        <td>Documents</td>
                                        <td>People &amp; Culture</td>
                                        <td><span class="ws-status warn">For review</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Acknowledge Operations Circular #2026-04</div>
                                            <div class="row-sub">Internal memo distribution &amp; receipt log</div>
                                        </td>
                                        <td>Memos</td>
                                        <td>Executive Office</td>
                                        <td><span class="ws-status">Awaiting sign-off</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Publish Monthly Organisation Update</div>
                                            <div class="row-sub">Public newsroom post and intake form</div>
                                        </td>
                                        <td>Sites &amp; Forms</td>
                                        <td>Communications</td>
                                        <td><span class="ws-status done">Ready</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- View 2: Projects --}}
                        <div class="workspace-view" id="view-projects" role="tabpanel" aria-labelledby="tab-projects">
                            <div class="view-header">
                                <div>
                                    <h3>Projects, tasks, and milestones.</h3>
                                    <p>Organise deliverables, assign owners, and log time without leaving the workspace.</p>
                                </div>
                                <div class="view-meta">PROJECTS &amp; TASKS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Open Tasks</div>
                                    <div class="m-val">24</div>
                                    <div class="m-sub">Across 4 active workstreams</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Milestones on Track</div>
                                    <div class="m-val">9 / 9</div>
                                    <div class="m-sub">Current quarter schedule</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Logged Hours</div>
                                    <div class="m-val">142h</div>
                                    <div class="m-sub">Recorded on timesheets</div>
                                </div>
                            </div>

                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Logged</th>
                                        <th>Stage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Finalise service intake workflow</div>
                                            <div class="row-sub">Connect public form submissions to project tasks</div>
                                        </td>
                                        <td>Client Operations</td>
                                        <td>14.5 hrs</td>
                                        <td><span class="ws-status">In progress</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Annual facility inspection checklist</div>
                                            <div class="row-sub">Coordinate maintenance work orders across offices</div>
                                        </td>
                                        <td>Facilities &amp; Assets</td>
                                        <td>8.0 hrs</td>
                                        <td><span class="ws-status warn">Under review</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Publish updated partner directory page</div>
                                            <div class="row-sub">Approved copy and media assets attached</div>
                                        </td>
                                        <td>Website Launch</td>
                                        <td>6.0 hrs</td>
                                        <td><span class="ws-status done">Completed</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- View 3: Documents & Memos --}}
                        <div class="workspace-view" id="view-documents" role="tabpanel" aria-labelledby="tab-documents">
                            <div class="view-header">
                                <div>
                                    <h3>Controlled documents and official memos.</h3>
                                    <p>Keep policies versioned, share files with the right roles, and track memo sign-offs.</p>
                                </div>
                                <div class="view-meta">DOCUMENTS &amp; MEMOS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Controlled Files</div>
                                    <div class="m-val">182</div>
                                    <div class="m-sub">Version history preserved</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Official Memos</div>
                                    <div class="m-val">14</div>
                                    <div class="m-sub">With recipient acknowledgement</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Access Control</div>
                                    <div class="m-val">Role-Based</div>
                                    <div class="m-sub">Scoped by department &amp; team</div>
                                </div>
                            </div>

                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Document / Memo</th>
                                        <th>Version</th>
                                        <th>Access</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Staff Handbook &amp; Leave Policy</div>
                                            <div class="row-sub">Updated annual and study leave guidelines</div>
                                        </td>
                                        <td>Version 3.2</td>
                                        <td>All Staff</td>
                                        <td><span class="ws-status done">Published</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Executive Circular #2026-04: Q4 Budget</div>
                                            <div class="row-sub">Mandatory department head acknowledgement</div>
                                        </td>
                                        <td>Memo</td>
                                        <td>Department Heads</td>
                                        <td><span class="ws-status">4 of 6 signed</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Vendor Service Level Agreement Template</div>
                                            <div class="row-sub">Standard contract for marketplace suppliers</div>
                                        </td>
                                        <td>Version 2.0</td>
                                        <td>Operations &amp; Legal</td>
                                        <td><span class="ws-status done">Approved</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- View 4: People & HR --}}
                        <div class="workspace-view" id="view-people" role="tabpanel" aria-labelledby="tab-people">
                            <div class="view-header">
                                <div>
                                    <h3>Support your people and teams.</h3>
                                    <p>Maintain employee records, departments, leave requests, and performance appraisals.</p>
                                </div>
                                <div class="view-meta">PEOPLE &amp; HR</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Team Directory</div>
                                    <div class="m-val">48</div>
                                    <div class="m-sub">6 structured departments</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Leave Requests</div>
                                    <div class="m-val">2</div>
                                    <div class="m-sub">Awaiting manager approval</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Appraisal Cycle</div>
                                    <div class="m-val">Active</div>
                                    <div class="m-sub">Quarterly reviews in progress</div>
                                </div>
                            </div>

                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Employee / Request</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Evelyn Asare — Annual Leave Request</div>
                                            <div class="row-sub">5 working days requested &middot; 16 days balance remaining</div>
                                        </td>
                                        <td>People &amp; Culture</td>
                                        <td>Leave Approval</td>
                                        <td><span class="ws-status warn">Awaiting review</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Kwame Osei — Q4 Performance Appraisal</div>
                                            <div class="row-sub">Self-assessment submitted for manager review</div>
                                        </td>
                                        <td>Facilities &amp; Operations</td>
                                        <td>Appraisal</td>
                                        <td><span class="ws-status">In progress</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">New Staff Onboarding Checklist</div>
                                            <div class="row-sub">Workspace access, department assignment, and handbook</div>
                                        </td>
                                        <td>Communications</td>
                                        <td>Directory</td>
                                        <td><span class="ws-status done">Completed</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- View 5: Sites & Forms --}}
                        <div class="workspace-view" id="view-sites" role="tabpanel" aria-labelledby="tab-sites">
                            <div class="view-header">
                                <div>
                                    <h3>Your public website and intake forms.</h3>
                                    <p>Publish pages, news posts, media, and forms right alongside your internal tools.</p>
                                </div>
                                <div class="view-meta">SITES &amp; FORMS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Published Pages</div>
                                    <div class="m-val">18</div>
                                    <div class="m-sub">Custom domain connected</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Active Forms</div>
                                    <div class="m-val">6</div>
                                    <div class="m-sub">Public &amp; internal surveys</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Recent Submissions</div>
                                    <div class="m-val">37</div>
                                    <div class="m-sub">Routed to team queues</div>
                                </div>
                            </div>

                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Page / Form</th>
                                        <th>Path</th>
                                        <th>Activity</th>
                                        <th>State</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Organisation Home &amp; Services</div>
                                            <div class="row-sub">Primary public landing page and navigation</div>
                                        </td>
                                        <td><code>/</code></td>
                                        <td>Updated this week</td>
                                        <td><span class="ws-status done">Published</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Client &amp; Partner Enquiry Form</div>
                                            <div class="row-sub">Structured intake form with notification routing</div>
                                        </td>
                                        <td><code>/forms/enquiry</code></td>
                                        <td>14 submissions</td>
                                        <td><span class="ws-status done">Accepting</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Newsroom &amp; Public Announcements</div>
                                            <div class="row-sub">Articles, press updates, and media library assets</div>
                                        </td>
                                        <td><code>/news</code></td>
                                        <td>3scheduled posts</td>
                                        <td><span class="ws-status">Active</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </figure>

            <div class="stage-caption" id="workbench-caption">
                <span>Illustrative workspace &middot; Sample content, not a live account</span>
                <span>Projects &middot; Documents &middot; Memos &middot; People &middot; Forms &middot; Sites</span>
            </div>
        </div>
    </section>

    {{-- CHAPTER 01 SEPARATION: Full-Bleed Interstitial Divider with Narrative Introduction --}}
    <div class="section-separator" id="tools" aria-label="Tools chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">01 / The Tools</span>
                <span class="sep-label">Connected Operations</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Purpose-built tools for everyday work, connected to one shared engine.</h2>
                <p class="sep-text">
                    Every organisation runs on projects, documents, decisions, and people. Instead of scattering your team across disconnected SaaS subscriptions with separate logins, Unified Digital Workspace provides dedicated operational tools that read and write to the same single source of truth.
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 01: THE TOOLS — Left-Aligned Architectural Grid --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="tools-title">
        <div class="tools-spec-bar">
            <div class="tools-spec-left">
                <strong id="tools-title">6 Integrated Core Modules</strong>
                <span>Shared role permissions &middot; Common document store &middot; Full audit trail</span>
            </div>
            <div class="tools-spec-right">
                <span>Tenant-Isolated Architecture</span>
            </div>
        </div>

        <div class="tools-matrix">
            <article class="tool-cell">
                <div>
                    <div class="tool-num">01</div>
                    <h3>Move work forward.</h3>
                    <p>Organise projects and tasks, track milestones and timesheets, and keep every deliverable attached to its context.</p>
                </div>
                <div class="tool-spec">Projects &amp; Tasks &middot; Time Tracking &middot; Workflows</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">02</div>
                    <h3>Keep the knowledge.</h3>
                    <p>Manage controlled document versions, share folders with the right people, and circulate official memos with sign-offs.</p>
                </div>
                <div class="tool-spec">Document Management &middot; Memos &middot; Intranet</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">03</div>
                    <h3>Support your people.</h3>
                    <p>Maintain employee records, structure departments and designations, and manage leave requests and appraisals.</p>
                </div>
                <div class="tool-spec">Employee Directory &middot; Leave &middot; Appraisals</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">04</div>
                    <h3>Put your work out there.</h3>
                    <p>Create and manage your public website with pages, posts, and media libraries alongside your internal workspace.</p>
                </div>
                <div class="tool-spec">CMS &amp; Publishing &middot; Custom Domains &middot; Media</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">05</div>
                    <h3>Collect structured requests.</h3>
                    <p>Build forms and surveys for clients, staff, or partners so incoming requests arrive organised and ready to action.</p>
                </div>
                <div class="tool-spec">Forms &amp; Surveys &middot; Submissions &middot; CRM Leads</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">06</div>
                    <h3>Run facilities &amp; operations.</h3>
                    <p>Track properties, leases, maintenance work orders, incidents, and vendor contracts in the same operational system.</p>
                </div>
                <div class="tool-spec">Facilities &middot; Real Estate &middot; Quality &amp; Vendors</div>
            </article>
        </div>
    </section>

    {{-- CHAPTER 02 SEPARATION: Full-Bleed Interstitial Divider with Architecture Introduction --}}
    <div class="section-separator sep-alt" id="why-unified" aria-label="Architecture chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">02 / Why Unified</span>
                <span class="sep-label">Operational Architecture</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Too many tools fragment the work. One workspace keeps it whole.</h2>
                <p class="sep-text">
                    When projects live in one app, documents in another, HR in a third, and your website somewhere else, people spend their day copying context between tabs. Unifying your digital workspace restores operational speed, clarity, and control.
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 02: WHY UNIFIED — Left-Aligned Architectural Comparison --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="why-title">
        <div class="ledger-grid">
            <div class="ledger-left">
                <div>
                    <div class="index-kicker">02 / The Comparison</div>
                    <h2 id="why-title">The Single-System Advantage</h2>
                    <p>
                        When teams buy point solutions in isolation, they create friction between departments. Here is how operating in a unified workspace changes daily execution.
                    </p>

                    <ul class="ledger-highlights">
                        <li>
                            <span class="lh-check" aria-hidden="true">&check;</span>
                            <div>
                                <strong>Zero context switching</strong>
                                <span>Tasks, files, and sign-offs in one interface.</span>
                            </div>
                        </li>
                        <li>
                            <span class="lh-check" aria-hidden="true">&check;</span>
                            <div>
                                <strong>One source of truth</strong>
                                <span>Shared staff directory with no duplicate profiles.</span>
                            </div>
                        </li>
                        <li>
                            <span class="lh-check" aria-hidden="true">&check;</span>
                            <div>
                                <strong>Predictable monthly billing</strong>
                                <span>One transparent statement with accountable usage.</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="ledger-callout">
                    <strong>Built as one coherent workspace</strong>
                    <span>One login, one role-based permission tree, and one monthly statement.</span>
                </div>
            </div>

            <div class="ledger-right">
                <div class="ledger-row ledger-row-header">
                    <div>Dimension</div>
                    <div>Disconnected Tools</div>
                    <div>Unified Digital Workspace</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Daily Work</div>
                    <div class="ledger-old">Tasks, files, and approvals scattered across separate subscriptions and inboxes.</div>
                    <div class="ledger-new">Projects, documents, memos, and requests connected in one shared workspace.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">People &amp; Access</div>
                    <div class="ledger-old">Duplicate user accounts and mismatched permissions across five different admin panels.</div>
                    <div class="ledger-new">One member directory and role-based access model across every enabled tool.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Documents</div>
                    <div class="ledger-old">Unclear file versions and attachments lost in chat threads with no audit trail.</div>
                    <div class="ledger-new">Version-controlled document folders, official memos, and clear activity records.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Public Web</div>
                    <div class="ledger-old">A separate CMS and form tool disconnected from the team handling the work.</div>
                    <div class="ledger-new">Your public website, media library, and forms managed right inside your workspace.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Billing</div>
                    <div class="ledger-old">Multiple recurring software invoices in different currencies and renewal cycles.</div>
                    <div class="ledger-new">Transparent website plans with predictable member access, module, and usage billing.</div>
                </div>
            </div>
        </div>
    </section>

    {{-- CHAPTER 03 SEPARATION: Full-Bleed Interstitial Divider with Pricing Introduction --}}
    <div class="section-separator" id="pricing" aria-label="Pricing chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">03 / Plans &amp; Pricing</span>
                <span class="sep-label">Predictable Growth</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">A website today. A wider workspace when you need it.</h2>
                <p class="sep-text">
                    Start with a reliable public website and intake forms today, then unlock internal workspace tools and member licences as your team expands. Predictable monthly billing with zero surprise fees and full data control.
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 03: THE ICONIC BLUE STATEMENT — Pricing --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="pricing-title">
        <div class="iconic-blue-band">
            <div class="iconic-blue-left">
                <div>
                    <div class="index-kicker">Transparent Pricing</div>
                    <h2 id="pricing-title">Simple, modular investment for growing organisations.</h2>
                    <p>
                        Start with an opt-in website plan, then see how member access, business modules, and usage shape your workspace estimate as your organisation grows.
                    </p>
                </div>

                <div class="iconic-blue-actions">
                    <a class="btn btn-lg btn-white" href="{{ $pricingUrl }}">See plans &amp; estimate costs &rarr;</a>
                </div>
            </div>

            <div class="iconic-blue-right">
                <div class="plan-ledger">
                    @foreach ($websitePlans as $plan)
                        <div class="plan-row">
                            <div>
                                <div class="plan-name">{{ $plan['name'] }}</div>
                                <div class="plan-sub">Public website, CMS publishing, forms &amp; workspace foundation</div>
                            </div>
                            <div class="plan-price">
                                GHS {{ number_format($plan['GHS'], 0) }} <small>/ month</small>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="plan-footnote">
                    Opt-in website plans. Setup, additional access and usage charges may apply. Business modules are priced separately. Existing agreements keep their agreed rates.
                </p>
            </div>
        </div>
    </section>

    {{-- CLOSING SEPARATION: Full-Bleed Interstitial Divider --}}
    <div class="section-separator sep-compact sep-alt" aria-label="Closing introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">Next Step</span>
                <span class="sep-label">Launch Workspace</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Give good work a clean, connected place to happen.</h2>
                <p class="sep-text">
                    Bring your projects, documents, people, and public presence into one shared workspace.
                </p>
            </div>
        </div>
    </div>

    {{-- CLOSING SECTION: Left-Aligned Action Bar --}}
    <section class="rail band" aria-labelledby="closing-title">
        <div class="closing-grid">
            <div class="closing-left">
                <h2 id="closing-title">Ready to unify your organisation's work?</h2>
                <p>Start with the tools your team needs today. Keep the work connected.</p>
            </div>
            <div class="closing-right">
                @if ($hasRegistration)
                    <a class="btn btn-lg" href="{{ route('register') }}">Create your account &rarr;</a>
                @elseif ($hasEnquiry)
                    <a class="btn btn-lg" href="{{ route('product.enterprise') }}">Plan your workspace &rarr;</a>
                @endif
                @if ($hasLogin)
                    <a class="btn btn-lg btn-outline" href="{{ route('login') }}">Log in to workspace</a>
                @endif
            </div>
        </div>
    </section>
</main>

<footer class="rail">
    <div class="footer-grid">
        <div class="footer-brand">Unified Digital Workspace</div>
        <nav class="footer-links" aria-label="Footer navigation">
            <a href="#workspace">Workspace</a>
            <a href="#tools">Tools</a>
            <a href="#why-unified">Why Unified</a>
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

<script>
    function selectWorkspaceView(viewKey) {
        const pathLabels = {
            overview: 'Workspace Overview',
            projects: 'Projects & Tasks',
            documents: 'Documents & Memos',
            people: 'People & Leave',
            sites: 'Sites & Forms'
        };

        document.querySelectorAll('.stage-tab').forEach(function (tab) {
            tab.setAttribute('aria-selected', tab.id === 'tab-' + viewKey ? 'true' : 'false');
        });

        document.querySelectorAll('.app-nav-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-nav') === viewKey);
        });

        document.querySelectorAll('.workspace-view').forEach(function (view) {
            view.classList.toggle('active', view.id === 'view-' + viewKey);
        });

        const pathLabel = document.getElementById('active-path-label');
        if (pathLabel && pathLabels[viewKey]) {
            pathLabel.textContent = pathLabels[viewKey];
        }
    }
</script>
</body>
</html>
