<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('seo-title', $theme->siteName())</title>

    @hasSection('seo')
        @yield('seo')
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if($theme->customCss())
        <style>{!! $theme->customCss() !!}</style>
    @endif

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-900 antialiased" style="--brand-color: {{ $theme->primaryColor() }}; --brand-secondary: {{ $theme->secondaryColor() }};">

    {{-- Corporate-style top bar --}}
    <div class="bg-gray-900 text-gray-300 text-xs">
        <div class="mx-auto flex max-w-7xl items-center justify-end gap-4 px-4 py-1.5 sm:px-6 lg:px-8">
            @if($theme->get('phone'))
                <span>{{ $theme->get('phone') }}</span>
            @endif
            @if($theme->get('email'))
                <a href="mailto:{{ $theme->get('email') }}" class="hover:text-white">{{ $theme->get('email') }}</a>
            @endif
        </div>
    </div>

    @include('cms-core::components.nav', [
        'menu' => $theme->headerMenu(),
        'logo' => $theme->logo(),
        'siteName' => $theme->siteName(),
    ])

    <main class="flex-1">
        @yield('content')
    </main>

    @include('cms-core::components.footer', [
        'menu' => $theme->footerMenu(),
        'footerText' => $theme->footerText(),
        'siteName' => $theme->siteName(),
    ])

    @stack('scripts')
</body>
</html>
