@php
    $menu = $theme->headerMenu();
    $menuItems = $menu?->items->whereNull('parent_id')->sortBy('sort_order') ?? collect();
    $resolveUrl = function (\App\Modules\CmsCore\Models\MenuItem $item) use ($cmsUrl): string {
        if ($item->relationLoaded('post') && $item->post) {
            return $item->post->slug === 'home'
                ? $cmsUrl->route('home')
                : $cmsUrl->route('pages.show', $item->post->slug);
        }

        return $cmsUrl->normalizeUrl($item->url) ?: $cmsUrl->route('home');
    };
    $contentSlug = $page->slug ?? 'home';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', $theme->siteName())</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="/assets/sites/lifepartners/argon-dashboard.css">
    <link rel="stylesheet" href="/assets/sites/lifepartners/preview.css?v=20261023-2">
</head>
<body class="lpp-body lpp-page-{{ $contentSlug }}">
    <a class="lpp-skip-link" href="#lpp-content">Skip to content</a>
    <aside class="lpp-preview-banner" aria-label="Website preview">
        Preview — Life Partners Platform. This website is being reviewed before launch.
    </aside>
    <header class="lpp-header">
        <div class="container lpp-header-inner">
            <a class="lpp-brand" href="{{ $cmsUrl->route('home') }}">
                <svg class="lpp-brand-symbol" width="44" height="44" viewBox="0 0 44 44" fill="none" aria-hidden="true">
                    <rect width="44" height="44" rx="14" fill="currentColor" />
                    <path d="M11 24c4-6 7 6 11 0s7 6 11 0M11 31c4-6 7 6 11 0s7 6 11 0" stroke="white" stroke-width="2.5" stroke-linecap="round" />
                    <circle cx="22" cy="14" r="4" fill="#F48A70" />
                </svg>
                <span>{{ $theme->siteName() }}<small>Community safety in Ghana</small></span>
            </a>
            @if($menuItems->isNotEmpty())
                <nav class="lpp-desktop-nav" aria-label="Main navigation">
                    @include('site-lifepartners-platform::public.partials.menu')
                </nav>
                <details class="lpp-mobile-menu">
                    <summary>Menu</summary>
                    <nav aria-label="Mobile navigation">
                        @include('site-lifepartners-platform::public.partials.menu')
                    </nav>
                </details>
            @endif
        </div>
    </header>
    <main id="lpp-content" class="lpp-content" tabindex="-1">
        @yield('content')
    </main>
    <footer class="lpp-footer">
        <div class="container lpp-footer-invitation">
            <div>
                <h2>Safer communities. Stronger futures.</h2>
                <p>Discover our work in prevention, education and community resilience.</p>
            </div>
            <a class="lpp-button" href="{{ $cmsUrl->route('pages.show', 'what-we-do') }}">Explore our programmes</a>
        </div>
        <div class="container lpp-footer-bottom">
            <div>
                <a class="lpp-footer-brand" href="{{ $cmsUrl->route('home') }}">{{ $theme->siteName() }}</a>
                <p>Copyright &copy; {{ date('Y') }} Life Partners Platform.</p>
            </div>
            @if($menuItems->isNotEmpty())
                <nav class="lpp-footer-nav" aria-label="Footer navigation">
                    @include('site-lifepartners-platform::public.partials.menu')
                </nav>
            @endif
        </div>
    </footer>
</body>
</html>
