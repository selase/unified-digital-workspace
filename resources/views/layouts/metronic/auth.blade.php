<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ config('app.name') }} | @yield('title')</title>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('assets/metronic/media/app/favicon.ico') }}"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"/>
    <link href="{{ asset('assets/metronic/vendors/keenicons/styles.bundle.css') }}" rel="stylesheet"/>
    <link href="{{ asset('assets/metronic/css/styles.css') }}" rel="stylesheet"/>
    @stack('custom-styles')
    @stack('styles')
    <style>
        .page-bg {
            background-image: url('{{ asset('assets/metronic/media/images/2600x1200/bg-10.png') }}');
        }

        .dark .page-bg {
            background-image: url('{{ asset('assets/metronic/media/images/2600x1200/bg-10-dark.png') }}');
        }

        .phpdebugbar-openhandler-overlay {
            display: none !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
    </style>

    @include('layouts.admin.partials.custom-styles')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-full text-foreground bg-background">
<script>
    const defaultThemeMode = 'light';
    let themeMode = localStorage.getItem('kt-theme') || defaultThemeMode;

    if (themeMode === 'system') {
        themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.classList.add(themeMode);
</script>

<div class="min-h-screen flex items-center justify-center bg-center bg-cover bg-no-repeat page-bg p-6">
    <div class="kt-card w-full max-w-[420px]">
        <div class="kt-card-content p-8 sm:p-10">
            @yield('content')
        </div>
    </div>
</div>

<script src="{{ asset('assets/metronic/js/core.bundle.js') }}"></script>
<script>
    const clearAuthStaleOverlays = () => {
        document.querySelectorAll('[data-kt-modal-backdrop], [data-kt-drawer-backdrop], .kt-modal-backdrop, .kt-drawer-backdrop, .modal-backdrop, .offcanvas-backdrop, .kt-drawer-overlay, .phpdebugbar-openhandler-overlay, .driver-overlay, .driver-popover').forEach((element) => {
            element.remove();
        });

        document.body.classList.remove('modal-open', 'offcanvas-open', 'driver-active');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    };

    document.addEventListener('DOMContentLoaded', clearAuthStaleOverlays);
    window.addEventListener('pageshow', clearAuthStaleOverlays);
    window.setTimeout(clearAuthStaleOverlays, 300);
    window.setTimeout(clearAuthStaleOverlays, 1200);
</script>
@stack('custom-scripts')
@stack('scripts')
</body>
</html>
