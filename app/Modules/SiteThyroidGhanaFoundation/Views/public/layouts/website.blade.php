<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('seo-title', $theme->siteName())</title>

    @hasSection('seo')
        @yield('seo')
    @endif

    @if($theme->favicon())
        <link rel="icon" href="{{ Storage::disk($theme->favicon()->disk)->url($theme->favicon()->path) }}" />
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --tgf-primary: #0D9488;
            --tgf-primary-dark: #0F766E;
            --tgf-accent: #B45309;
            --tgf-accent-light: #D97706;
            --tgf-dark: #0F172A;
            --tgf-text: #1E293B;
            --tgf-muted: #64748B;
            --tgf-light: #F8FAFC;
            --tgf-border: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .tgf-btn-primary {
            background: var(--tgf-primary);
            color: #fff;
            padding: 0.75rem 2rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
            transition: all 0.2s;
            display: inline-block;
        }
        .tgf-btn-primary:hover { background: var(--tgf-primary-dark); }
        .tgf-btn-outline {
            border: 1.5px solid var(--tgf-primary);
            color: var(--tgf-primary);
            padding: 0.75rem 2rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s;
            display: inline-block;
        }
        .tgf-btn-outline:hover { background: var(--tgf-primary); color: #fff; }
        .tgf-btn-accent {
            background: var(--tgf-accent);
            color: #fff;
            padding: 0.75rem 2rem;
            border-radius: 0.375rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s;
            display: inline-block;
        }
        .tgf-btn-accent:hover { background: var(--tgf-accent-light); }
        {!! $theme->customCss() !!}
    </style>

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-white antialiased" style="color: var(--tgf-text);">

    {{-- Top utility bar --}}
    <div style="background: var(--tgf-dark);" class="text-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 sm:px-6 lg:px-8">
            <div class="flex items-center gap-4 text-slate-400">
                <span>+233 (024) 337 6304</span>
                <span class="hidden sm:inline">&middot;</span>
                <span class="hidden sm:inline">info@thyroidghanafoundation.org</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="#" class="text-slate-400 transition hover:text-white" aria-label="Facebook">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="#" class="text-slate-400 transition hover:text-white" aria-label="Twitter">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Main navigation --}}
    <header class="sticky top-0 z-50 border-b bg-white/95 backdrop-blur-sm" style="border-color: var(--tgf-border);">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ $cmsUrl->route('home') }}" class="flex items-center gap-3">
                @if($theme->logo())
                    <img src="{{ Storage::disk($theme->logo()->disk)->url($theme->logo()->path) }}" alt="{{ $theme->siteName() }}" class="h-10 w-auto" />
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background: var(--tgf-primary);">
                        <span class="text-lg font-bold text-white">TGF</span>
                    </div>
                @endif
                <div>
                    <span class="text-lg font-bold" style="color: var(--tgf-dark);">Thyroid Ghana</span>
                    <span class="block text-xs font-medium" style="color: var(--tgf-muted);">Foundation</span>
                </div>
            </a>

            @php $headerMenu = $theme->headerMenu(); @endphp
            @if($headerMenu && $headerMenu->items->isNotEmpty())
                <nav class="hidden items-center gap-8 lg:flex">
                    @foreach($headerMenu->items->whereNull('parent_id')->sortBy('sort_order') as $item)
                        @php
                            $href = $item->url ?: ($item->post_id && $item->post
                                ? ($item->post->postType?->slug === 'page'
                                    ? $cmsUrl->route('pages.show', $item->post->slug)
                                    : $cmsUrl->route('posts.show', $item->post->slug))
                                : '#');
                        @endphp
                        <a href="{{ $href }}" class="text-sm font-medium transition hover:opacity-70" style="color: var(--tgf-text);">
                            {{ $item->label }}
                        </a>
                    @endforeach
                    <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent">
                        Donate
                    </a>
                </nav>

                {{-- Mobile toggle --}}
                <button type="button" class="lg:hidden rounded-md p-2" style="color: var(--tgf-text);" x-data x-on:click="$dispatch('toggle-mobile-menu')" aria-label="Menu">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            @endif
        </div>

        {{-- Mobile menu --}}
        @if($headerMenu && $headerMenu->items->isNotEmpty())
            <div class="border-t lg:hidden" style="border-color: var(--tgf-border);" x-data="{ open: false }" x-on:toggle-mobile-menu.window="open = !open" x-show="open" x-cloak>
                <nav class="space-y-1 px-4 py-3">
                    @foreach($headerMenu->items->whereNull('parent_id')->sortBy('sort_order') as $item)
                        @php
                            $href = $item->url ?: ($item->post_id && $item->post
                                ? ($item->post->postType?->slug === 'page'
                                    ? $cmsUrl->route('pages.show', $item->post->slug)
                                    : $cmsUrl->route('posts.show', $item->post->slug))
                                : '#');
                        @endphp
                        <a href="{{ $href }}" class="block rounded-md px-3 py-2 text-sm font-medium" style="color: var(--tgf-text);">{{ $item->label }}</a>
                    @endforeach
                    <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent block text-center mt-2">Donate</a>
                </nav>
            </div>
        @endif
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer style="background: var(--tgf-dark);" class="text-slate-400">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Brand --}}
                <div class="lg:col-span-1">
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg" style="background: var(--tgf-primary);">
                            <span class="text-sm font-bold text-white">TGF</span>
                        </div>
                        <span class="text-lg font-bold text-white">Thyroid Ghana Foundation</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed">
                        Creating awareness of thyroid diseases in Ghana and providing access to affordable treatment for all.
                    </p>
                </div>

                {{-- Quick links --}}
                <div>
                    <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">About</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="transition hover:text-white">Our Mission</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'the-challenge') }}" class="transition hover:text-white">The Challenge</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'our-team') }}" class="transition hover:text-white">Our Team</a></li>
                        <li><a href="{{ $cmsUrl->route('posts.archive') }}" class="transition hover:text-white">News</a></li>
                    </ul>
                </div>

                {{-- Get involved --}}
                <div>
                    <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Get Involved</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="transition hover:text-white">Donate</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="transition hover:text-white">Volunteer</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'partner-us') }}" class="transition hover:text-white">Partner With Us</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'membership') }}" class="transition hover:text-white">Membership</a></li>
                    </ul>
                </div>

                {{-- Contact --}}
                <div>
                    <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Contact</h4>
                    <ul class="space-y-2 text-sm">
                        <li>112, Lane 10, Onyinase Street</li>
                        <li>Awoshie, Accra, Ghana</li>
                        <li class="pt-1">+233 (024) 337 6304</li>
                        <li>info@thyroidghanafoundation.org</li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 border-t border-slate-800 pt-8 flex flex-wrap items-center justify-between gap-4">
                <p class="text-xs">&copy; {{ date('Y') }} Thyroid Ghana Foundation. All rights reserved.</p>
                <p class="text-xs">Member of Thyroid Federation International</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
