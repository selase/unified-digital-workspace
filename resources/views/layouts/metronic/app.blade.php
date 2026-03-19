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
    $currentRouteName = (string) (request()->route()?->getName() ?? '');
    $currentModuleSlug = \Illuminate\Support\Str::before($currentRouteName, '.');
    $activeSidebarModule = collect($navigation['sidebar']['modules'] ?? [])->first(function (array $module) use ($currentModuleSlug): bool {
        if (($module['is_active'] ?? false) === true) {
            return true;
        }

        return ($module['slug'] ?? null) === $currentModuleSlug;
    });

    $activeModuleLabel = $navigation['activeModule']['label'] ?? ($activeSidebarModule['label'] ?? null);
    $topMenuGroups = collect($navigation['topMenuGroups'] ?? []);

    if ($topMenuGroups->isEmpty() && $activeSidebarModule) {
        $fallbackItems = collect($activeSidebarModule['items'] ?? [])
            ->map(fn (array $item): array => [
                'label' => (string) ($item['label'] ?? 'Overview'),
                'url' => (string) ($item['url'] ?? '#'),
                'is_active' => (bool) ($item['is_active'] ?? false),
            ])
            ->filter(fn (array $item): bool => $item['url'] !== '#')
            ->values();

        if ($fallbackItems->isNotEmpty()) {
            $topMenuGroups = collect([
                [
                    'label' => (string) ($activeSidebarModule['label'] ?? 'Module'),
                    'items' => $fallbackItems->all(),
                ],
            ]);
        }
    }

    $quickAccessLinks = $topMenuGroups
        ->flatMap(function (array $group): \Illuminate\Support\Collection {
            return collect($group['items'] ?? [])
                ->map(fn (array $item): array => [
                    'label' => $item['label'] ?? 'Open',
                    'url' => $item['url'] ?? '#',
                ]);
        })
        ->filter(fn (array $item): bool => filled($item['url']) && $item['url'] !== '#')
        ->unique('url')
        ->take(8)
        ->values();

    $appShortcuts = collect($navigation['sidebar']['modules'] ?? [])
        ->map(function (array $module): array {
            return [
                'label' => $module['label'] ?? 'Module',
                'icon' => $module['icon'] ?? 'ki-filled ki-grid',
                'url' => $module['items'][0]['url'] ?? '#',
                'description' => count($module['items'] ?? []).' pages',
                'active' => (bool) ($module['is_active'] ?? false),
            ];
        })
        ->take(8)
        ->values();
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
                        <div class="kt-menu-item {{ $link['is_active'] ? 'active' : '' }}">
                            <a
                                class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:kt-menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                href="{{ $link['url'] }}"
                                tabindex="0"
                            >
                                <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary"></span>
                                <span class="kt-menu-title text-2sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">{{ $link['label'] }}</span>
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
                            <div class="kt-menu-item {{ $link['is_active'] ? 'active' : '' }}">
                                <a
                                    class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:kt-menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                    href="{{ $link['url'] }}"
                                    tabindex="0"
                                >
                                    <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary"></span>
                                    <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">{{ $link['label'] }}</span>
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
                            <div class="kt-menu-item {{ $module['is_active'] ? 'here show' : '' }}" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
                                <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
                                    <span class="kt-menu-icon items-start text-muted-foreground w-[20px] kt-menu-item-here:text-primary">
                                        <i class="{{ $module['icon'] }} text-lg"></i>
                                    </span>
                                    <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-item-here:text-primary kt-menu-link-hover:!text-primary">{{ $module['label'] }}</span>
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
                                        <div class="kt-menu-item {{ $link['is_active'] ? 'active' : '' }}">
                                            <a
                                                class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:kt-menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                                href="{{ $link['url'] }}"
                                                tabindex="0"
                                            >
                                                <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary"></span>
                                                <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">{{ $link['label'] }}</span>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @elseif(!$navigation['isTenantContext'])
                        <div class="kt-menu-item px-[10px] pt-3">
                            <div class="rounded-lg border border-dashed border-border bg-accent/30 px-3 py-2 text-xs text-muted-foreground">
                                Module workspaces show after switching into a tenant context.
                                @if(\Illuminate\Support\Facades\Route::has('tenant.my-tenants'))
                                    <a class="text-primary hover:underline" href="{{ route('tenant.my-tenants') }}">Open My Tenants</a>.
                                @endif
                            </div>
                        </div>
                    @endif

                    @if(!empty($navigation['sidebar']['tenant_groups']))
                        <div class="kt-menu-item pt-2.25 pb-px">
                            <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
                                Tenant
                            </span>
                        </div>
                        @foreach($navigation['sidebar']['tenant_groups'] as $group)
                            <div class="kt-menu-item {{ $group['is_active'] ? 'here show' : '' }}" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
                                <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
                                    <span class="kt-menu-icon items-start text-muted-foreground w-[20px] kt-menu-item-here:text-primary">
                                        <i class="{{ $group['icon'] }} text-lg"></i>
                                    </span>
                                    <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-item-here:text-primary kt-menu-link-hover:!text-primary">{{ $group['label'] }}</span>
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
                                    @foreach($group['items'] as $link)
                                        <div class="kt-menu-item {{ $link['is_active'] ? 'active' : '' }}">
                                            <a
                                                class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:kt-menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]"
                                                href="{{ $link['url'] }}"
                                                tabindex="0"
                                            >
                                                <span class="kt-menu-bullet flex w-[6px] -start-[3px] relative before:absolute before:top-0 before:size-[6px] before:rounded-full before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary"></span>
                                                <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">{{ $link['label'] }}</span>
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

    <div class="kt-wrapper flex grow flex-col">
        <header class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background" data-kt-sticky="true" data-kt-sticky-class="" data-kt-sticky-name="header" id="header">
            <div class="kt-container-fixed flex justify-between items-stretch lg:gap-4" id="headerContainer">
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

                    <div class="block min-w-0">
                        <div class="kt-menu flex items-center gap-2 overflow-x-auto whitespace-nowrap" data-kt-menu="true" id="mega_menu">
                            @foreach($topMenuGroups as $group)
                                @php
                                    $groupHasActiveItem = collect($group['items'])->contains(fn (array $item): bool => (bool) ($item['is_active'] ?? false));
                                @endphp
                                <div
                                    class="kt-menu-item {{ $groupHasActiveItem ? 'here' : '' }}"
                                    data-kt-menu-item-placement="bottom-start"
                                    data-kt-menu-item-placement-rtl="bottom-end"
                                    data-kt-menu-item-toggle="accordion|lg:dropdown"
                                    data-kt-menu-item-trigger="click|lg:hover"
                                >
                                    <button class="kt-menu-link py-3 px-3 text-sm font-medium text-foreground kt-menu-item-here:text-primary hover:text-primary" type="button">
                                        <span class="kt-menu-title">{{ $group['label'] }}</span>
                                        <span class="kt-menu-arrow text-muted-foreground">
                                            <i class="ki-filled ki-down text-xs"></i>
                                        </span>
                                    </button>
                                    <div class="kt-menu-dropdown kt-menu-default w-full max-w-[440px] py-2">
                                        <div class="kt-menu-item px-2">
                                            <div class="grid gap-1">
                                                <h3 class="text-xs uppercase tracking-wide text-muted-foreground px-3 py-2">{{ $group['label'] }}</h3>
                                                <div class="grid md:grid-cols-2 gap-1">
                                                    @foreach($group['items'] as $item)
                                                        <a class="kt-menu-link rounded-lg px-3 py-2 {{ $item['is_active'] ? 'bg-primary/10 text-primary' : '' }}" href="{{ $item['url'] }}">
                                                            <span class="kt-menu-title text-sm">{{ $item['label'] }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
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

                    <button class="kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-drawer-toggle="#notifications_drawer" type="button">
                        <i class="ki-filled ki-notification-status text-lg"></i>
                    </button>

                    <button class="kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-drawer-toggle="#chat_drawer" type="button">
                        <i class="ki-filled ki-messages text-lg"></i>
                    </button>

                    <div data-kt-dropdown="true" data-kt-dropdown-offset="10px, 10px" data-kt-dropdown-placement="bottom-end" data-kt-dropdown-placement-rtl="bottom-start">
                        <button class="kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary kt-dropdown-open:bg-primary/10 kt-dropdown-open:[&_i]:text-primary" data-kt-dropdown-toggle="true" type="button">
                            <i class="ki-filled ki-element-11 text-lg"></i>
                        </button>
                        <div class="kt-dropdown-menu p-0 w-screen max-w-[320px]" data-kt-dropdown-menu="true">
                            <div class="flex items-center justify-between gap-2.5 text-xs text-secondary-foreground font-medium px-5 py-3 border-b border-b-border">
                                <span>Workspace Apps</span>
                                <span>{{ $appShortcuts->count() }} enabled</span>
                            </div>
                            <div class="flex flex-col kt-scrollable-y-auto max-h-[360px] divide-y divide-border">
                                @forelse($appShortcuts as $shortcut)
                                    <a class="flex items-center justify-between gap-2 px-5 py-3.5 hover:bg-accent/60 transition-colors" href="{{ $shortcut['url'] }}">
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-10">
                                                <i class="{{ $shortcut['icon'] }} text-base"></i>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-semibold text-mono">{{ $shortcut['label'] }}</span>
                                                <span class="text-xs font-medium text-secondary-foreground">{{ $shortcut['description'] }}</span>
                                            </div>
                                        </div>
                                        @if($shortcut['active'])
                                            <span class="kt-badge kt-badge-sm kt-badge-primary">Live</span>
                                        @endif
                                    </a>
                                @empty
                                    <div class="px-5 py-5 text-sm text-muted-foreground">No tenant modules enabled yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0" data-kt-dropdown="true" data-kt-dropdown-offset="10px, 10px" data-kt-dropdown-placement="bottom-end" data-kt-dropdown-placement-rtl="bottom-start" data-kt-dropdown-trigger="click">
                        <div class="cursor-pointer shrink-0" data-kt-dropdown-toggle="true">
                            <img alt="{{ $userName }}" class="size-9 rounded-full border-2 border-green-500 shrink-0" src="{{ $userAvatarUrl }}"/>
                        </div>
                        <div class="kt-dropdown-menu w-[280px]" data-kt-dropdown-menu="true">
                            <div class="flex items-center justify-between px-2.5 py-1.5 gap-1.5">
                                <div class="flex items-center gap-2">
                                    <img alt="{{ $userName }}" class="size-9 shrink-0 rounded-full border-2 border-green-500" src="{{ $userAvatarUrl }}"/>
                                    <div class="flex flex-col gap-1.5">
                                        <span class="text-sm text-foreground font-semibold leading-none">{{ $userName }}</span>
                                        <span class="text-xs text-secondary-foreground font-medium leading-none">{{ $userEmail }}</span>
                                    </div>
                                </div>
                                <span class="kt-badge kt-badge-sm kt-badge-primary kt-badge-outline">
                                    {{ $tenant ? 'Tenant' : 'Admin' }}
                                </span>
                            </div>
                            <ul class="kt-dropdown-menu-sub">
                                <li><div class="kt-dropdown-menu-separator"></div></li>
                                @if($profileUrl)
                                    <li>
                                        <a class="kt-dropdown-menu-link" href="{{ $profileUrl }}">
                                            <i class="ki-filled ki-profile-circle"></i>
                                            My Profile
                                        </a>
                                    </li>
                                @endif
                                <li>
                                    <a class="kt-dropdown-menu-link" href="{{ $navigation['homeUrl'] }}">
                                        <i class="ki-filled ki-home-2"></i>
                                        Dashboard
                                    </a>
                                </li>
                                <li data-kt-dropdown="true" data-kt-dropdown-placement="right-start" data-kt-dropdown-trigger="hover">
                                    <button class="kt-dropdown-menu-toggle" data-kt-dropdown-toggle="true" type="button">
                                        <i class="ki-filled ki-setting-2"></i>
                                        My Account
                                        <span class="kt-dropdown-menu-indicator">
                                            <i class="ki-filled ki-right text-xs"></i>
                                        </span>
                                    </button>
                                    <div class="kt-dropdown-menu w-[220px]" data-kt-dropdown-menu="true">
                                        <ul class="kt-dropdown-menu-sub">
                                            @foreach($quickAccessLinks->take(5) as $quickAccessLink)
                                                <li>
                                                    <a class="kt-dropdown-menu-link" href="{{ $quickAccessLink['url'] }}">
                                                        <i class="ki-filled ki-some-files"></i>
                                                        {{ $quickAccessLink['label'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </li>
                                <li>
                                    <div class="kt-dropdown-menu-separator"></div>
                                </li>
                            </ul>
                            <div class="px-2.5 pt-1.5 mb-2.5 flex flex-col gap-3.5">
                                <div class="flex items-center gap-2 justify-between">
                                    <span class="flex items-center gap-2">
                                        <i class="ki-filled ki-moon text-base text-muted-foreground"></i>
                                        <span class="font-medium text-2sm">Dark Mode</span>
                                    </span>
                                    <input class="kt-switch" data-kt-theme-switch-state="dark" data-kt-theme-switch-toggle="true" name="check" type="checkbox" value="1"/>
                                </div>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button class="kt-btn kt-btn-outline justify-center w-full" type="submit">
                                        Log out
                                    </button>
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

<div class="hidden kt-drawer kt-drawer-end card flex-col max-w-[90%] w-[420px] top-5 bottom-5 end-5 rounded-xl border border-border" data-kt-drawer="true" data-kt-drawer-container="body" id="notifications_drawer">
    <div class="flex items-center justify-between gap-2.5 text-sm text-mono font-semibold px-5 py-3 border-b border-b-border">
        Notifications
        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-dim shrink-0" data-kt-drawer-dismiss="true" type="button">
            <i class="ki-filled ki-cross"></i>
        </button>
    </div>
    <div class="kt-scrollable-y-auto grow px-5 py-4 space-y-4" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="120px">
        <div class="rounded-lg border border-border bg-accent/40 p-3">
            <p class="text-sm font-semibold text-foreground">Workspace ready</p>
            <p class="text-xs text-muted-foreground mt-1">Metronic header actions are enabled for this workspace.</p>
        </div>
        @forelse($quickAccessLinks->take(5) as $quickAccessLink)
            <a class="flex items-center justify-between rounded-lg border border-border bg-background px-3 py-2 hover:bg-accent/50 transition-colors" href="{{ $quickAccessLink['url'] }}">
                <span class="text-sm font-medium text-foreground">{{ $quickAccessLink['label'] }}</span>
                <i class="ki-filled ki-right text-xs text-muted-foreground"></i>
            </a>
        @empty
            <p class="text-sm text-muted-foreground">No new notifications right now.</p>
        @endforelse
    </div>
</div>

<div class="hidden kt-drawer kt-drawer-end card flex-col max-w-[90%] w-[420px] top-5 bottom-5 end-5 rounded-xl border border-border" data-kt-drawer="true" data-kt-drawer-container="body" id="chat_drawer">
    <div class="flex items-center justify-between gap-2.5 text-sm text-mono font-semibold px-5 py-3 border-b border-b-border">
        Team Chat
        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-dim shrink-0" data-kt-drawer-dismiss="true" type="button">
            <i class="ki-filled ki-cross"></i>
        </button>
    </div>
    <div class="kt-scrollable-y-auto grow px-5 py-4 space-y-3" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="120px">
        <div class="rounded-lg border border-border bg-accent/40 p-3">
            <p class="text-sm font-semibold text-foreground">Channel: Workspace Operations</p>
            <p class="text-xs text-muted-foreground mt-1">Use this drawer as the quick communication hub while we complete module pages.</p>
        </div>
        <div class="rounded-lg border border-border p-3">
            <p class="text-sm font-medium text-foreground">Quick links</p>
            <div class="mt-3 space-y-2">
                @foreach($quickAccessLinks->take(4) as $quickAccessLink)
                    <a class="block text-sm text-primary hover:underline" href="{{ $quickAccessLink['url'] }}">{{ $quickAccessLink['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
    <div class="border-t border-border p-4">
        <div class="kt-input">
            <input placeholder="Write a message..." type="text" value=""/>
            <button class="kt-btn kt-btn-mono kt-btn-sm" type="button">Send</button>
        </div>
    </div>
</div>

<script src="{{ asset('assets/metronic/js/core.bundle.js') }}"></script>
<script src="{{ asset('assets/metronic/vendors/ktui/ktui.min.js') }}"></script>
<script src="{{ asset('assets/metronic/vendors/apexcharts/apexcharts.min.js') }}"></script>
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

    const normalizeWorkspaceChrome = () => {
        document.body.classList.remove('kt-sidebar-hide', 'kt-sidebar-hidden', 'kt-sidebar-collapse', 'kt-sidebar-collapsed');

        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            // Only force-show sidebar on desktop (lg+). On mobile the drawer mechanism controls visibility.
            if (window.matchMedia('(min-width: 1024px)').matches) {
                sidebar.classList.remove('hidden');
            }
            sidebar.style.removeProperty('display');
            sidebar.style.removeProperty('visibility');
            sidebar.style.removeProperty('opacity');
        }
    };

    const forceResetUiLayers = () => {
        clearStaleUiOverlays();
        normalizeWorkspaceChrome();
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
