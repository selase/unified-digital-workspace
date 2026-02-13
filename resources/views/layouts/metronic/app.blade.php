<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>@yield('title', config('app.name'))</title>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ route('metronic.assets', ['path' => 'media/app/favicon.ico']) }}" rel="shortcut icon"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="{{ route('metronic.assets', ['path' => 'vendors/apexcharts/apexcharts.css']) }}" rel="stylesheet"/>
    <link href="{{ route('metronic.assets', ['path' => 'vendors/keenicons/styles.bundle.css']) }}" rel="stylesheet"/>
    <link href="{{ route('metronic.assets', ['path' => 'css/styles.css']) }}" rel="stylesheet"/>
    @stack('styles')
</head>
<body class="antialiased flex min-h-full text-base text-foreground bg-background [--header-height:64px] [--sidebar-width:270px] bg-mono">
<script>
    const defaultThemeMode = 'light';
    let themeMode = localStorage.getItem('kt-theme') || defaultThemeMode;

    if (themeMode === 'system') {
        themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.classList.add(themeMode);
</script>

@php
    $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
    $subdomain = $tenant?->slug;
@endphp

<div class="flex grow">
    <header class="flex lg:hidden items-center fixed z-10 top-0 start-0 end-0 shrink-0 bg-mono h-(--header-height)">
        <div class="kt-container-fixed flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-semibold">U</div>
                <span class="text-sm font-semibold">Unified Digital Workspace</span>
            </div>
            <button class="kt-btn kt-btn-icon kt-btn-dim hover:text-white -me-2" data-kt-drawer-toggle="#metronic_sidebar">
                <i class="ki-filled ki-menu"></i>
            </button>
        </div>
    </header>

    <div class="flex flex-col lg:flex-row grow pt-(--header-height) lg:pt-0">
        <aside class="flex-col fixed top-0 bottom-0 z-20 hidden lg:flex items-stretch shrink-0 w-(--sidebar-width) bg-background border-e border-border"
               data-kt-drawer="true"
               data-kt-drawer-class="kt-drawer kt-drawer-start flex top-0 bottom-0"
               data-kt-drawer-overlay="true"
               data-kt-drawer-permanent="true"
               id="metronic_sidebar">
            <div class="flex flex-col gap-6 h-full">
                <div class="flex items-center gap-3 px-5 h-[72px]">
                    <div class="size-10 rounded-full bg-primary text-primary-foreground flex items-center justify-center font-semibold">U</div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted-foreground">UDW</div>
                        <div class="text-lg font-semibold text-foreground">Metronic UI</div>
                    </div>
                </div>

                <nav class="flex flex-col gap-2 px-4">
                    @foreach(config('metronic.module_pages', []) as $module)
                        @php
                            $routeName = $module['route'] ?? null;
                            $routeTarget = null;
                            if ($routeName && \Illuminate\Support\Facades\Route::has($routeName)) {
                                $routeInstance = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
                                $parameterNames = $routeInstance?->parameterNames() ?? [];
                                $routeParams = [];
                                if (in_array('subdomain', $parameterNames, true)) {
                                    if ($subdomain) {
                                        $routeParams['subdomain'] = $subdomain;
                                    }
                                }

                                if (empty(array_diff($parameterNames, array_keys($routeParams)))) {
                                    $routeTarget = route($routeName, $routeParams);
                                }
                            }
                        @endphp

                        @if($routeTarget)
                            <a class="kt-btn kt-btn-sm kt-btn-outline w-full justify-start" href="{{ $routeTarget }}">
                                {{ $module['label'] }}
                            </a>
                        @else
                            <div class="kt-btn kt-btn-sm kt-btn-outline w-full justify-start opacity-60 cursor-not-allowed">
                                {{ $module['label'] }}
                            </div>
                        @endif
                    @endforeach
                </nav>

                <div class="mt-auto px-5 pb-6 text-xs text-muted-foreground">
                    Demo {{ config('metronic.demo') }} page inventory.
                </div>
            </div>
        </aside>

        <main class="flex-1 overflow-auto">
            <div class="kt-container-fixed px-6 lg:px-10 py-6 lg:py-10">
                @yield('content')
            </div>
        </main>
    </div>
</div>

<script src="{{ route('metronic.assets', ['path' => 'js/core.bundle.js']) }}"></script>
@stack('scripts')
</body>
</html>
