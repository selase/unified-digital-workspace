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

    @if($theme->favicon())
        <link rel="icon" href="{{ $theme->favicon()->url() }}" />
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if($theme->customCss())
        <style>{!! $theme->customCss() !!}</style>
    @endif

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col bg-white text-gray-900 antialiased" style="--brand-color: {{ $theme->primaryColor() }}; --brand-secondary: {{ $theme->secondaryColor() }};">

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
