<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ config('app.name') }} | @yield('title')</title>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('assets/metronic/media/app/favicon.ico') }}"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"/>
    <link href="{{ asset('assets/metronic/vendors/apexcharts/apexcharts.css') }}" rel="stylesheet"/>
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
<body class="antialiased flex h-full text-base text-foreground bg-background demo1 kt-sidebar-fixed kt-header-fixed">
<script>
    const defaultThemeMode = 'light';
    let themeMode = localStorage.getItem('kt-theme') || defaultThemeMode;

    if (themeMode === 'system') {
        themeMode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    document.documentElement.classList.add(themeMode);
</script>

@php
    $navigation = app(\App\Services\Navigation\WorkspaceNavigation::class)->forRequest(request());
    $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
    $user = auth()->user();
    $profileUrl = null;

    if ($user && \Illuminate\Support\Facades\Route::has('profile.index')) {
        $profileUrl = route('profile.index', $user);
    }

    $userPhoto = null;
    if ($user instanceof \App\Models\User) {
        $attributes = $user->getAttributes();
        $userPhoto = array_key_exists('photo', $attributes) ? $attributes['photo'] : null;
    }

    if ($userPhoto) {
        $userAvatarUrl = \Illuminate\Support\Facades\Storage::url($userPhoto);
    } elseif ($user && $user->gravatar) {
        $userAvatarUrl = $user->gravatar;
    } else {
        $userAvatarUrl = asset('assets/metronic/media/avatars/300-2.png');
    }

    $userName = $user?->displayName() ?? 'User';
    $userEmail = $user?->email ?? '';
    $activeModuleLabel = $navigation['activeModule']['label'] ?? null;
@endphp

<div class="flex grow">
    <aside
        class="kt-sidebar bg-background border-e border-e-border fixed top-0 bottom-0 z-20 hidden lg:flex flex-col items-stretch shrink-0 [--kt-drawer-enable:true] lg:[--kt-drawer-enable:false]"
        data-kt-drawer="true"
        data-kt-drawer-class="kt-drawer kt-drawer-start top-0 bottom-0"
        id="sidebar"
    >
        <div class="kt-sidebar-header hidden lg:flex items-center relative justify-between px-3 lg:px-6 shrink-0" id="sidebar_header">
            <a class="dark:hidden" href="{{ $navigation['homeUrl'] }}">
                <img class="default-logo min-h-[22px] max-w-none" src="{{ asset('assets/metronic/media/app/default-logo.svg') }}" alt="Logo"/>
                <img class="small-logo min-h-[22px] max-w-none" src="{{ asset('assets/metronic/media/app/mini-logo.svg') }}" alt="Logo"/>
            </a>
            <a class="hidden dark:block" href="{{ $navigation['homeUrl'] }}">
                <img class="default-logo min-h-[22px] max-w-none" src="{{ asset('assets/metronic/media/app/default-logo-dark.svg') }}" alt="Logo"/>
                <img class="small-logo min-h-[22px] max-w-none" src="{{ asset('assets/metronic/media/app/mini-logo.svg') }}" alt="Logo"/>
            </a>
            <button
                class="kt-btn kt-btn-outline kt-btn-icon size-[30px] absolute start-full top-2/4 -translate-x-2/4 -translate-y-2/4 rtl:translate-x-2/4"
                data-kt-toggle="body"
                data-kt-toggle-class="kt-sidebar-collapse"
                id="sidebar_toggle"
            >
                <i class="ki-filled ki-black-left-line kt-toggle-active:rotate-180 transition-all duration-300 rtl:translate rtl:rotate-180 rtl:kt-toggle-active:rotate-0"></i>
            </button>
        </div>

        <div class="kt-sidebar-content flex grow shrink-0 py-5 pe-2" id="sidebar_content">
            <div
                class="kt-scrollable-y-hover grow shrink-0 flex ps-2 lg:ps-5 pe-1 lg:pe-3"
                data-kt-scrollable="true"
                data-kt-scrollable-dependencies="#sidebar_header"
                data-kt-scrollable-height="auto"
                data-kt-scrollable-offset="0px"
                data-kt-scrollable-wrappers="#sidebar_content"
                id="sidebar_scrollable"
            >
                <div class="kt-menu flex flex-col grow gap-1" data-kt-menu="true" data-kt-menu-accordion-expand-all="false" id="sidebar_menu">
                    <div class="kt-menu-item pt-2.25 pb-px">
                        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
                            Dashboards
                        </span>
                    </div>
                    @foreach($navigation['sidebar']['dashboards'] as $link)
                        <div class="kt-menu-item">
                            <a
                                class="kt-menu-link border border-transparent items-center grow {{ $link['is_active'] ? 'bg-accent/60 rounded-lg' : 'hover:bg-accent/60 hover:rounded-lg' }} gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                href="{{ $link['url'] }}"
                                tabindex="0"
                            >
                                <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 {{ $link['is_active'] ? 'before:bg-primary' : '' }}"></span>
                                <span class="kt-menu-title text-2sm font-medium text-foreground {{ $link['is_active'] ? 'text-primary' : '' }}">{{ $link['label'] }}</span>
                            </a>
                        </div>
                    @endforeach

                    @if(!empty($navigation['sidebar']['superadmin']))
                        <div class="kt-menu-item pt-2.25 pb-px">
                            <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
                                Superadmin
                            </span>
                        </div>
                        @foreach($navigation['sidebar']['superadmin'] as $link)
                            <div class="kt-menu-item">
                                <a
                                    class="kt-menu-link border border-transparent items-center grow {{ $link['is_active'] ? 'bg-accent/60 rounded-lg' : 'hover:bg-accent/60 hover:rounded-lg' }} gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                    href="{{ $link['url'] }}"
                                    tabindex="0"
                                >
                                    <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 {{ $link['is_active'] ? 'before:bg-primary' : '' }}"></span>
                                    <span class="kt-menu-title text-2sm font-normal text-foreground {{ $link['is_active'] ? 'text-primary font-semibold' : '' }}">{{ $link['label'] }}</span>
                                </a>
                            </div>
                        @endforeach
                    @endif

                    @if(!empty($navigation['sidebar']['tenant']))
                        <div class="kt-menu-item pt-2.25 pb-px">
                            <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
                                Tenant
                            </span>
                        </div>
                        @foreach($navigation['sidebar']['tenant'] as $link)
                            <div class="kt-menu-item">
                                <a
                                    class="kt-menu-link border border-transparent items-center grow {{ $link['is_active'] ? 'bg-accent/60 rounded-lg' : 'hover:bg-accent/60 hover:rounded-lg' }} gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                    href="{{ $link['url'] }}"
                                    tabindex="0"
                                >
                                    <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 {{ $link['is_active'] ? 'before:bg-primary' : '' }}"></span>
                                    <span class="kt-menu-title text-2sm font-normal text-foreground {{ $link['is_active'] ? 'text-primary font-semibold' : '' }}">{{ $link['label'] }}</span>
                                </a>
                            </div>
                        @endforeach
                    @endif

                    @if(!empty($navigation['sidebar']['modules']))
                        <div class="kt-menu-item pt-2.25 pb-px">
                            <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
                                Modules
                            </span>
                        </div>
                        @foreach($navigation['sidebar']['modules'] as $module)
                            <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
                                <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px] {{ $module['is_active'] ? 'bg-accent/50 rounded-lg' : '' }}" tabindex="0">
                                    <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
                                        <i class="{{ $module['icon'] }} text-lg"></i>
                                    </span>
                                    <span class="kt-menu-title text-sm font-medium text-foreground {{ $module['is_active'] ? 'text-primary' : '' }}">{{ $module['label'] }}</span>
                                    <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
                                        <span class="inline-flex kt-menu-item-show:hidden">
                                            <i class="ki-filled ki-plus text-[11px]"></i>
                                        </span>
                                        <span class="hidden kt-menu-item-show:inline-flex">
                                            <i class="ki-filled ki-minus text-[11px]"></i>
                                        </span>
                                    </span>
                                </div>
                                <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
                                    @foreach($module['items'] as $link)
                                        <div class="kt-menu-item">
                                            <a
                                                class="kt-menu-link border border-transparent items-center grow {{ $link['is_active'] ? 'bg-accent/60 rounded-lg' : 'hover:bg-accent/60 hover:rounded-lg' }} gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                                href="{{ $link['url'] }}"
                                                tabindex="0"
                                            >
                                                <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 {{ $link['is_active'] ? 'before:bg-primary' : '' }}"></span>
                                                <span class="kt-menu-title text-2sm font-normal text-foreground {{ $link['is_active'] ? 'text-primary font-semibold' : '' }}">{{ $link['label'] }}</span>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </aside>

    <div class="flex grow flex-col">
        <header class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background border-b border-border" id="header">
            <div class="kt-container-fixed flex items-center justify-between gap-4" id="headerContainer">
                <div class="flex items-center gap-3 lg:gap-6">
                    <button
                        class="kt-btn kt-btn-icon kt-btn-outline shrink-0 lg:hidden"
                        data-kt-drawer-toggle="#sidebar"
                        type="button"
                    >
                        <i class="ki-filled ki-menu"></i>
                    </button>

                    <div class="hidden lg:flex items-center gap-3">
                        @if($activeModuleLabel)
                            <span class="kt-badge kt-badge-outline">{{ $activeModuleLabel }}</span>
                        @else
                            <span class="text-sm font-medium text-muted-foreground">Workspace</span>
                        @endif
                    </div>

                    <div class="hidden lg:block">
                        <div class="kt-menu flex items-center gap-2" data-kt-menu="true" id="mega_menu">
                            @foreach($navigation['topMenuGroups'] as $group)
                                <div
                                    class="kt-menu-item"
                                    data-kt-menu-item-placement="bottom-start"
                                    data-kt-menu-item-placement-rtl="bottom-end"
                                    data-kt-menu-item-toggle="accordion|lg:dropdown"
                                    data-kt-menu-item-trigger="click|lg:hover"
                                >
                                    <button class="kt-menu-link py-3 px-3 text-sm font-medium text-foreground hover:text-primary" type="button">
                                        <span class="kt-menu-title">{{ $group['label'] }}</span>
                                        <span class="kt-menu-arrow text-muted-foreground">
                                            <i class="ki-filled ki-down text-xs"></i>
                                        </span>
                                    </button>
                                    <div class="kt-menu-dropdown kt-menu-default w-full max-w-[320px] py-2">
                                        <div class="kt-menu-item px-2">
                                            <div class="grid gap-1">
                                                @foreach($group['items'] as $item)
                                                    <a class="kt-menu-link rounded-lg px-3 py-2 {{ $item['is_active'] ? 'bg-primary/10 text-primary' : '' }}" href="{{ $item['url'] }}">
                                                        <span class="kt-menu-title text-sm">{{ $item['label'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 lg:gap-3.5">
                    <button class="group kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-modal-toggle="#search_modal" type="button">
                        <i class="ki-filled ki-magnifier text-lg"></i>
                    </button>

                    <div class="kt-dropdown" data-kt-dropdown="true" data-kt-dropdown-placement="bottom-end">
                        <button class="group kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-dropdown-toggle="true" type="button">
                            <i class="ki-filled ki-notification-status text-lg"></i>
                        </button>
                        <div class="kt-dropdown-menu w-[300px] p-4 space-y-3">
                            <p class="text-sm font-semibold text-foreground">Notifications</p>
                            <p class="text-xs text-muted-foreground">No new notifications right now.</p>
                        </div>
                    </div>

                    <div class="kt-dropdown" data-kt-dropdown="true" data-kt-dropdown-placement="bottom-end">
                        <button class="size-10 kt-dropdown-toggle rounded-full border border-border shrink-0" data-kt-dropdown-toggle="true" type="button">
                            <img class="rounded-full" src="{{ $userAvatarUrl }}" alt="{{ $userName }}">
                        </button>
                        <div class="kt-dropdown-menu w-[280px] p-4">
                            <div class="flex items-center gap-3 border-b border-border pb-3 mb-3">
                                <img class="size-11 rounded-full" src="{{ $userAvatarUrl }}" alt="{{ $userName }}">
                                <div>
                                    <p class="text-sm font-semibold text-foreground">{{ $userName }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $userEmail }}</p>
                                    @if($tenant)
                                        <p class="text-xs text-muted-foreground mt-1">{{ $tenant->name }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="space-y-1">
                                @if($profileUrl)
                                    <a class="kt-btn kt-btn-outline w-full justify-start" href="{{ $profileUrl }}">My Profile</a>
                                @endif
                                <a class="kt-btn kt-btn-outline w-full justify-start" href="{{ $navigation['homeUrl'] }}">Dashboard</a>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="kt-btn kt-btn-primary w-full justify-start" type="submit">Log out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="grow pt-5" id="content" role="content">
            <div class="kt-container-fixed">
                @yield('content')
            </div>
        </main>
    </div>
</div>

<div class="kt-modal" data-kt-modal="true" id="search_modal">
    <div class="kt-modal-content max-w-[640px] top-[10%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Quick Search</h3>
            <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-light" data-kt-modal-dismiss="true" type="button">
                <i class="ki-filled ki-cross"></i>
            </button>
        </div>
        <div class="kt-modal-body space-y-4">
            <p class="text-sm text-muted-foreground">Use global search shortcuts to find tenants, users, invoices, and module pages.</p>
            <input class="kt-input" placeholder="Search workspace..." type="text">
            <div class="rounded-lg border border-border p-3 text-xs text-muted-foreground">
                Search service is available from the command menu and tenant workspace.
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/metronic/js/core.bundle.js') }}"></script>
<script src="{{ asset('assets/metronic/vendors/ktui/ktui.min.js') }}"></script>
<script src="{{ asset('assets/metronic/js/layouts/demo1.js') }}"></script>
<script>
    const clearStaleUiOverlays = () => {
        document.querySelectorAll('[data-kt-modal-backdrop], [data-kt-drawer-backdrop], .kt-modal-backdrop, .kt-drawer-backdrop, .modal-backdrop, .offcanvas-backdrop, .kt-drawer-overlay, .phpdebugbar-openhandler-overlay').forEach((element) => {
            element.remove();
        });

        document.querySelectorAll('.driver-overlay, .driver-popover').forEach((element) => {
            element.remove();
        });

        document.body.classList.remove('modal-open', 'offcanvas-open', 'driver-active');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    };

    const forceResetUiLayers = () => {
        if (typeof KTModal !== 'undefined' && typeof KTModal.hide === 'function') {
            KTModal.hide();
        }

        if (typeof KTDrawer !== 'undefined' && typeof KTDrawer.hide === 'function') {
            KTDrawer.hide();
        }

        clearStaleUiOverlays();
    };

    document.addEventListener('DOMContentLoaded', forceResetUiLayers);
    document.addEventListener('livewire:navigated', forceResetUiLayers);
    document.addEventListener('turbo:load', forceResetUiLayers);
    window.addEventListener('pageshow', forceResetUiLayers);
    window.setTimeout(forceResetUiLayers, 300);
    window.setTimeout(forceResetUiLayers, 1200);
</script>

@stack('vendor-scripts')

@if(session()->has('message'))
    <script>
        if (typeof toastr !== 'undefined') {
            const type = "{{ Session::get('status', 'success') }}";
            const message = "{{ Session::get('message') }}";

            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": true,
                "progressBar": true,
                "positionClass": "toastr-top-right",
                "preventDuplicates": false,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };

            if (type === 'warning') {
                toastr.warning(message, 'Warning');
            } else if (type === 'error') {
                toastr.error(message, 'Error');
            } else if (type === 'info') {
                toastr.info(message, 'Information');
            } else {
                toastr.success(message, 'Success');
            }
        }
    </script>
@endif

@stack('modals')
@stack('custom-scripts')
@stack('scripts')
</body>
</html>
