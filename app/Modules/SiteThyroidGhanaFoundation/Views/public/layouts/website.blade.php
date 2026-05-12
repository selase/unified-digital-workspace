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
        <link rel="icon" href="{{ $theme->favicon()->url() }}" />
        <link rel="apple-touch-icon" href="{{ $theme->favicon()->url() }}" />
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --tgf-primary: #0D9488;
            --tgf-primary-dark: #0F766E;
            --tgf-primary-light: #CCFBF1;
            --tgf-accent: #B45309;
            --tgf-accent-light: #D97706;
            --tgf-dark: #0F172A;
            --tgf-text: #1E293B;
            --tgf-muted: #64748B;
            --tgf-light: #F8FAFC;
            --tgf-border: #E2E8F0;
        }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .tgf-btn-primary { background: var(--tgf-primary); color: #fff; padding: 0.75rem 2rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; letter-spacing: 0.025em; transition: all 0.2s; display: inline-block; }
        .tgf-btn-primary:hover { background: var(--tgf-primary-dark); }
        .tgf-btn-outline { border: 1.5px solid var(--tgf-primary); color: var(--tgf-primary); padding: 0.75rem 2rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .tgf-btn-outline:hover { background: var(--tgf-primary); color: #fff; }
        .tgf-btn-accent { background: var(--tgf-accent); color: #fff; padding: 0.75rem 2rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; display: inline-block; }
        .tgf-btn-accent:hover { background: var(--tgf-accent-light); }
        .tgf-dropdown { position: relative; }
        .tgf-dropdown-menu { display: none; position: absolute; top: 100%; left: 0; min-width: 220px; background: #fff; border: 1px solid var(--tgf-border); border-radius: 0.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 0.5rem 0; z-index: 60; }
        .tgf-dropdown:hover .tgf-dropdown-menu { display: block; }
        .tgf-dropdown-menu a { display: block; padding: 0.5rem 1.25rem; font-size: 0.875rem; color: var(--tgf-text); transition: all 0.15s; }
        .tgf-dropdown-menu a:hover { background: var(--tgf-light); color: var(--tgf-primary); }
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
                <a href="https://www.facebook.com/thyroidghana" class="text-slate-400 transition hover:text-white" aria-label="Facebook" target="_blank" rel="noopener">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="https://www.youtube.com/@thyroidghanafoundation588" class="text-slate-400 transition hover:text-white" aria-label="YouTube" target="_blank" rel="noopener">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- Main navigation with dropdowns --}}
    <header class="sticky top-0 z-50 border-b bg-white/95 backdrop-blur-sm" style="border-color: var(--tgf-border);" x-data="{ mobileOpen: false }">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            {{-- Logo --}}
            <a href="{{ $cmsUrl->route('home') }}" class="flex items-center gap-3">
                @if($theme->logo())
                    <img src="{{ $theme->logo()->url() }}" alt="{{ $theme->siteName() }}" class="h-12 w-auto" />
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" style="background: var(--tgf-primary);">
                        <span class="text-lg font-bold text-white">TGF</span>
                    </div>
                @endif
                <div class="hidden sm:block">
                    <span class="text-lg font-bold" style="color: var(--tgf-dark);">Thyroid Ghana</span>
                    <span class="block text-xs font-medium" style="color: var(--tgf-muted);">Foundation</span>
                </div>
            </a>

            {{-- Desktop nav with dropdowns --}}
            <nav class="hidden items-center gap-1 lg:flex">
                <a href="{{ $cmsUrl->route('home') }}" class="rounded-md px-3 py-2 text-sm font-medium transition hover:bg-slate-50" style="color: var(--tgf-text);">Home</a>

                {{-- About dropdown --}}
                <div class="tgf-dropdown">
                    <a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition hover:bg-slate-50" style="color: var(--tgf-text);">
                        About
                        <svg class="h-3.5 w-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </a>
                    <div class="tgf-dropdown-menu">
                        <a href="{{ $cmsUrl->route('pages.show', 'about') }}">About Us</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'the-founder') }}">The Founder</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'our-team') }}">Our Team</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'our-plan') }}">Our Plan</a>
                    </div>
                </div>

                {{-- Thyroid Health dropdown --}}
                <div class="tgf-dropdown">
                    <a href="{{ $cmsUrl->route('pages.show', 'the-challenge') }}" class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition hover:bg-slate-50" style="color: var(--tgf-text);">
                        Thyroid Health
                        <svg class="h-3.5 w-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </a>
                    <div class="tgf-dropdown-menu">
                        <a href="{{ $cmsUrl->route('pages.show', 'the-challenge') }}">The Challenge</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'thyroid-disease') }}">Thyroid Disease</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'do-i-have-thyroid-disease') }}">Do I Have Thyroid Disease?</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'faq') }}">FAQ</a>
                    </div>
                </div>

                {{-- News & Media dropdown --}}
                <div class="tgf-dropdown">
                    <a href="{{ $cmsUrl->route('posts.archive') }}" class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition hover:bg-slate-50" style="color: var(--tgf-text);">
                        News & Media
                        <svg class="h-3.5 w-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </a>
                    <div class="tgf-dropdown-menu">
                        <a href="{{ $cmsUrl->route('posts.archive') }}">Latest News</a>
                        <a href="{{ $cmsUrl->route('newsletters.archive') }}">Newsletters</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'media-gallery') }}">Media Gallery</a>
                    </div>
                </div>

                {{-- Get Involved dropdown --}}
                <div class="tgf-dropdown">
                    <a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium transition hover:bg-slate-50" style="color: var(--tgf-text);">
                        Get Involved
                        <svg class="h-3.5 w-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </a>
                    <div class="tgf-dropdown-menu">
                        <a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}">Volunteer</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'partner-us') }}">Partner With Us</a>
                        <a href="{{ $cmsUrl->route('pages.show', 'membership') }}">Membership</a>
                    </div>
                </div>

                <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent ml-3">Donate</a>
            </nav>

            {{-- Mobile toggle --}}
            <button type="button" class="rounded-md p-2 lg:hidden" style="color: var(--tgf-text);" @click="mobileOpen = !mobileOpen" aria-label="Menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div class="border-t lg:hidden" style="border-color: var(--tgf-border);" x-show="mobileOpen" x-cloak x-transition>
            <nav class="space-y-1 px-4 py-3">
                <a href="{{ $cmsUrl->route('home') }}" class="block rounded-md px-3 py-2 text-sm font-medium">Home</a>
                <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--tgf-muted);">About</p>
                <a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="block rounded-md px-3 py-2 text-sm">About Us</a>
                <a href="{{ $cmsUrl->route('pages.show', 'the-founder') }}" class="block rounded-md px-3 py-2 text-sm">The Founder</a>
                <a href="{{ $cmsUrl->route('pages.show', 'our-team') }}" class="block rounded-md px-3 py-2 text-sm">Our Team</a>
                <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--tgf-muted);">Thyroid Health</p>
                <a href="{{ $cmsUrl->route('pages.show', 'the-challenge') }}" class="block rounded-md px-3 py-2 text-sm">The Challenge</a>
                <a href="{{ $cmsUrl->route('pages.show', 'thyroid-disease') }}" class="block rounded-md px-3 py-2 text-sm">Thyroid Disease</a>
                <a href="{{ $cmsUrl->route('pages.show', 'faq') }}" class="block rounded-md px-3 py-2 text-sm">FAQ</a>
                <p class="px-3 pt-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--tgf-muted);">News & Media</p>
                <a href="{{ $cmsUrl->route('posts.archive') }}" class="block rounded-md px-3 py-2 text-sm">Latest News</a>
                <a href="{{ $cmsUrl->route('newsletters.archive') }}" class="block rounded-md px-3 py-2 text-sm">Newsletters</a>
                <a href="{{ $cmsUrl->route('pages.show', 'media-gallery') }}" class="block rounded-md px-3 py-2 text-sm">Media Gallery</a>
                <div class="pt-3">
                    <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent block text-center">Donate</a>
                </div>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer style="background: var(--tgf-dark);" class="text-slate-400">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-1">
                    <div class="flex items-center gap-2">
                        @if($theme->logo())
                            <img src="{{ $theme->logo()->url() }}" alt="{{ $theme->siteName() }}" class="h-10 w-auto rounded bg-white p-1" />
                        @endif
                        <span class="text-lg font-bold text-white">Thyroid Ghana Foundation</span>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed">Creating awareness of thyroid diseases in Ghana and providing access to affordable treatment for all.</p>
                    <p class="mt-3 text-xs">Member of Thyroid Federation International</p>
                </div>
                <div>
                    <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">About</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ $cmsUrl->route('pages.show', 'about') }}" class="transition hover:text-white">About Us</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'the-founder') }}" class="transition hover:text-white">The Founder</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'our-team') }}" class="transition hover:text-white">Our Team</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'our-plan') }}" class="transition hover:text-white">Our Plan</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="mb-4 text-sm font-semibold uppercase tracking-wider text-white">Get Involved</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="transition hover:text-white">Donate</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="transition hover:text-white">Volunteer</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'partner-us') }}" class="transition hover:text-white">Partner With Us</a></li>
                        <li><a href="{{ $cmsUrl->route('pages.show', 'membership') }}" class="transition hover:text-white">Membership</a></li>
                    </ul>
                </div>
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
                <div class="flex items-center gap-4">
                    <a href="https://www.facebook.com/thyroidghana" class="text-slate-500 transition hover:text-white" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://www.youtube.com/@thyroidghanafoundation588" class="text-slate-500 transition hover:text-white" target="_blank" rel="noopener" aria-label="YouTube">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
