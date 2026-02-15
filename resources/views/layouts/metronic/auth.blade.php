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
        .phpdebugbar-openhandler-overlay {
            display: none !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
    </style>

    @include('layouts.admin.partials.custom-styles')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-full bg-mono text-foreground">
<script>
    const defaultThemeMode = 'light';
    let themeMode = localStorage.getItem('kt-theme') || defaultThemeMode;

    if (themeMode === 'system') {
        themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.classList.add(themeMode);
</script>

<div class="min-h-screen grid lg:grid-cols-2">
    <section class="hidden lg:flex flex-col justify-between bg-primary text-primary-foreground p-12">
        <div>
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-white/20 flex items-center justify-center font-semibold">U</div>
                <div class="text-lg font-semibold">Unified Digital Workspace</div>
            </div>
            <h1 class="mt-12 text-3xl font-semibold leading-tight">
                Workspaces, workflows, and insights in one place.
            </h1>
            <p class="mt-4 text-sm text-primary-foreground/80">
                Secure multi-tenant operations for HRMS, documents, incidents, and forums.
            </p>
        </div>
        <div class="text-xs text-primary-foreground/70">
            {{ \App\Libraries\Helper::getCurrentYear() }} - Powered by UDW.
        </div>
    </section>

    <section class="flex flex-col justify-center p-6 sm:p-10 lg:p-16">
        <div class="max-w-md w-full mx-auto">
            @yield('content')
        </div>
    </section>
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
