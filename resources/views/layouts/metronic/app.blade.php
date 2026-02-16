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
    $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
    $subdomain = $tenant?->slug;
    $user = auth()->user();
    $userPhoto = null;

    if ($user instanceof \App\Models\User) {
        $attributes = $user->getAttributes();
        $userPhoto = array_key_exists('photo', $attributes) ? $attributes['photo'] : null;
    }

    $userAvatarUrl = null;
    if ($userPhoto) {
        $userAvatarUrl = \Illuminate\Support\Facades\Storage::url($userPhoto);
    } elseif ($user && $user->gravatar) {
        $userAvatarUrl = $user->gravatar;
    } else {
        $userAvatarUrl = asset('assets/metronic/media/avatars/300-2.png');
    }

    $userName = $user?->displayName() ?? 'User';
    $userEmail = $user?->email ?? '';

    $logoLight = asset('assets/metronic/media/app/default-logo.svg');
    $logoDark = asset('assets/metronic/media/app/default-logo-dark.svg');
    $miniLogo = asset('assets/metronic/media/app/mini-logo.svg');

    $buildRoute = function (?string $routeName) use ($subdomain): ?string {
        if (! $routeName || ! \Illuminate\Support\Facades\Route::has($routeName)) {
            return null;
        }

        $routeInstance = \Illuminate\Support\Facades\Route::getRoutes()->getByName($routeName);
        $parameterNames = $routeInstance?->parameterNames() ?? [];
        $routeParams = [];

        if (in_array('subdomain', $parameterNames, true) && $subdomain) {
            $routeParams['subdomain'] = $subdomain;
        }

        if (! empty(array_diff($parameterNames, array_keys($routeParams)))) {
            return null;
        }

        return route($routeName, $routeParams);
    };

    $resolveRoute = function (string $routeName, array $params = []) use ($buildRoute): ?string {
        if (! \Illuminate\Support\Facades\Route::has($routeName)) {
            return null;
        }

        if (! empty($params)) {
            return route($routeName, $params);
        }

        return $buildRoute($routeName);
    };

    $homeRouteName = $subdomain ? 'tenant.dashboard' : 'dashboard';
    $homeUrl = $buildRoute($homeRouteName) ?? '#';
    $adminDashboardUrl = $buildRoute('dashboard');
    $tenantDashboardUrl = $buildRoute('tenant.dashboard');

    $settingsUrl = $buildRoute('tenant.settings.index');
    $billingSettingsUrl = $buildRoute('tenant.settings.billing');
    $paymentSettingsUrl = $buildRoute('tenant.settings.payments.index');
    $financeUrl = $buildRoute('tenant.finance.index');
    $usersUrl = $buildRoute($subdomain ? 'tenant.users.index' : 'users.index');
    $rolesUrl = $buildRoute($subdomain ? 'tenant.roles.index' : 'roles.index');
    $apiKeysUrl = $buildRoute($subdomain ? 'tenant.api-keys.index' : null);
    $llmUsageUrl = $buildRoute($subdomain ? 'tenant.llm-usage.index' : null);
    $llmConfigUrl = $buildRoute($subdomain ? 'tenant.llm-config.index' : null);

    $profileUrl = null;
    if ($user && \Illuminate\Support\Facades\Route::has('profile.index')) {
        $profileUrl = route('profile.index', $user);
    }

    $isTenantContext = filled($subdomain);
    $showSuperadminLinks = (bool) ($user?->can('access-superadmin-dashboard'));

    $superadminNavLinks = [
        ['label' => 'Tenants', 'url' => $buildRoute('tenants.index')],
        ['label' => 'Users', 'url' => $buildRoute('users.index')],
        ['label' => 'Roles', 'url' => $buildRoute('roles.index')],
        ['label' => 'Features', 'url' => $buildRoute('features.index')],
        ['label' => 'Packages', 'url' => $buildRoute('packages.index')],
        ['label' => 'Leads', 'url' => $buildRoute('admin.leads.index')],
        ['label' => 'Billing Transactions', 'url' => $buildRoute('admin.billing.transactions.index')],
        ['label' => 'Billing Subscriptions', 'url' => $buildRoute('admin.billing.subscriptions.index')],
        ['label' => 'Rate Cards', 'url' => $buildRoute('admin.billing.rate-cards.index')],
        ['label' => 'Invoices', 'url' => $buildRoute('admin.billing.invoices.index')],
        ['label' => 'Usage Analytics', 'url' => $buildRoute('admin.billing.analytics.usage')],
        ['label' => 'Global LLM Usage', 'url' => $buildRoute('llm-usage.index')],
        ['label' => 'Audit Activity', 'url' => $buildRoute('audit-trail.activity-logs.index')],
        ['label' => 'Audit Login History', 'url' => $buildRoute('audit-trail.login-history.index')],
        ['label' => 'Tenant Health', 'url' => $buildRoute('health.tenants')],
        ['label' => 'Application Health', 'url' => $buildRoute('application.health')],
        ['label' => 'Developer Tokens', 'url' => $buildRoute('settings.developer.tokens.index')],
    ];

    $tenantNavLinks = [
        ['label' => 'Tenant Dashboard', 'url' => $buildRoute('tenant.dashboard')],
        ['label' => 'Organization Settings', 'url' => $settingsUrl],
        ['label' => 'Billing Settings', 'url' => $billingSettingsUrl],
        ['label' => 'Payment Methods', 'url' => $paymentSettingsUrl],
        ['label' => 'Users', 'url' => $buildRoute('tenant.users.index')],
        ['label' => 'Roles', 'url' => $buildRoute('tenant.roles.index')],
        ['label' => 'Finance', 'url' => $financeUrl],
        ['label' => 'Tenant Billing', 'url' => $buildRoute('billing.index')],
        ['label' => 'Pricing', 'url' => $buildRoute('tenant.pricing')],
        ['label' => 'API Keys', 'url' => $apiKeysUrl],
        ['label' => 'LLM Usage', 'url' => $llmUsageUrl],
        ['label' => 'LLM Configuration', 'url' => $llmConfigUrl],
        ['label' => 'My Tenants', 'url' => $buildRoute('tenant.my-tenants')],
    ];

    $moduleNavLinks = [
        ['label' => 'Document Management', 'url' => $buildRoute('document-management.index')],
        ['label' => 'Memos', 'url' => $buildRoute('memos.index')],
        ['label' => 'Forums', 'url' => $buildRoute('forums.hub')],
        ['label' => 'Incident Management', 'url' => $buildRoute('incident-management.index')],
        ['label' => 'HRMS', 'url' => $buildRoute('hrms-core.index')],
        ['label' => 'CMS', 'url' => $buildRoute('cms-core.index')],
        ['label' => 'Project Management', 'url' => $buildRoute('project-management.')],
        ['label' => 'Quality Monitoring', 'url' => $buildRoute('quality-monitoring.')],
    ];
@endphp

<div class="flex grow">
    <div class="kt-sidebar bg-background border-e border-e-border fixed top-0 bottom-0 z-20 hidden lg:flex flex-col items-stretch shrink-0 [--kt-drawer-enable:true] lg:[--kt-drawer-enable:false]"
         data-kt-drawer="true"
         data-kt-drawer-class="kt-drawer kt-drawer-start top-0 bottom-0"
         id="sidebar">
        <div class="kt-sidebar-header hidden lg:flex items-center relative justify-between px-3 lg:px-6 shrink-0" id="sidebar_header">
            <a class="dark:hidden" href="{{ $homeUrl }}">
                <img class="default-logo min-h-[22px] max-w-none" src="{{ $logoLight }}" alt="Logo"/>
                <img class="small-logo min-h-[22px] max-w-none" src="{{ $miniLogo }}" alt="Logo"/>
            </a>
            <a class="hidden dark:block" href="{{ $homeUrl }}">
                <img class="default-logo min-h-[22px] max-w-none" src="{{ $logoDark }}" alt="Logo"/>
                <img class="small-logo min-h-[22px] max-w-none" src="{{ $miniLogo }}" alt="Logo"/>
            </a>
            <button class="kt-btn kt-btn-outline kt-btn-icon size-[30px] absolute start-full top-2/4 -translate-x-2/4 -translate-y-2/4 rtl:translate-x-2/4"
                    data-kt-toggle="body"
                    data-kt-toggle-class="kt-sidebar-collapse"
                    id="sidebar_toggle">
                <i class="ki-filled ki-black-left-line kt-toggle-active:rotate-180 transition-all duration-300 rtl:translate rtl:rotate-180 rtl:kt-toggle-active:rotate-0"></i>
            </button>
        </div>
        <div class="kt-sidebar-content flex grow shrink-0 py-5 pe-2" id="sidebar_content">
            <div class="kt-scrollable-y-hover grow shrink-0 flex ps-2 lg:ps-5 pe-1 lg:pe-3"
                 data-kt-scrollable="true"
                 data-kt-scrollable-dependencies="#sidebar_header"
                 data-kt-scrollable-height="auto"
                 data-kt-scrollable-offset="0px"
                 data-kt-scrollable-wrappers="#sidebar_content"
                 id="sidebar_scrollable">
                <div class="kt-menu flex flex-col grow gap-1" data-kt-menu="true" data-kt-menu-accordion-expand-all="false" id="sidebar_menu">
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-element-11 text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          Dashboards
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="{{ $adminDashboardUrl ?? '#' }}" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Admin Dashboard
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="{{ $tenantDashboardUrl ?? '#' }}" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Tenant Dashboard
           </span>
          </a>
         </div>
        </div>
       </div>
       <div class="kt-menu-item pt-2.25 pb-px">
        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
         Workspace
        </span>
       </div>
       @if ($showSuperadminLinks)
        <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
         <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
          <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
           <i class="ki-filled ki-shield-tick text-lg">
           </i>
          </span>
          <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
           Superadmin
          </span>
          <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
           <span class="inline-flex kt-menu-item-show:hidden">
            <i class="ki-filled ki-plus text-[11px]">
            </i>
           </span>
           <span class="hidden kt-menu-item-show:inline-flex">
            <i class="ki-filled ki-minus text-[11px]">
            </i>
           </span>
          </span>
         </div>
         <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
          @foreach ($superadminNavLinks as $navLink)
           @if ($navLink['url'])
            <div class="kt-menu-item">
             <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="{{ $navLink['url'] }}" tabindex="0">
              <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
              </span>
              <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
               {{ $navLink['label'] }}
              </span>
             </a>
            </div>
           @endif
          @endforeach
         </div>
        </div>
       @endif
       @if ($isTenantContext)
        <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
         <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
          <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
           <i class="ki-filled ki-abstract-26 text-lg">
           </i>
          </span>
          <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
           Tenant
          </span>
          <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
           <span class="inline-flex kt-menu-item-show:hidden">
            <i class="ki-filled ki-plus text-[11px]">
            </i>
           </span>
           <span class="hidden kt-menu-item-show:inline-flex">
            <i class="ki-filled ki-minus text-[11px]">
            </i>
           </span>
          </span>
         </div>
         <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
          @foreach ($tenantNavLinks as $navLink)
           @if ($navLink['url'])
            <div class="kt-menu-item">
             <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="{{ $navLink['url'] }}" tabindex="0">
              <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
              </span>
              <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
               {{ $navLink['label'] }}
              </span>
             </a>
            </div>
           @endif
          @endforeach
         </div>
        </div>
       @endif
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-grid text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          Modules
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         @foreach ($moduleNavLinks as $moduleLink)
          @if ($moduleLink['url'])
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="{{ $moduleLink['url'] }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              {{ $moduleLink['label'] }}
             </span>
            </a>
           </div>
          @endif
         @endforeach
        </div>
       </div>
       <div class="kt-menu-item pt-2.25 pb-px">
        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
         User
        </span>
       </div>
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-profile-circle text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          Public Profile
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Profiles
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $profileUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Default
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $settingsUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Creator
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $billingSettingsUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Company
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $usersUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              NFT
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Blogger
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              CRM
             </span>
            </a>
           </div>
           <div class="kt-menu-item flex-col-reverse" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
            <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[5px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-secondary-foreground">
              <span class="hidden kt-menu-item-show:!flex">
               Show less
              </span>
              <span class="flex kt-menu-item-show:hidden">
               Show 4 more
              </span>
             </span>
             <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
              <span class="inline-flex kt-menu-item-show:hidden">
               <i class="ki-filled ki-plus text-[11px]">
               </i>
              </span>
              <span class="hidden kt-menu-item-show:inline-flex">
               <i class="ki-filled ki-minus text-[11px]">
               </i>
              </span>
             </span>
            </div>
            <div class="kt-menu-accordion gap-1">
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Gamer
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Feeds
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Plain
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Modal
               </span>
              </a>
             </div>
            </div>
           </div>
          </div>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Projects
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              3 Columns
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              2 Columns
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Works
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Teams
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Network
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Activity
           </span>
          </a>
         </div>
         <div class="kt-menu-item flex-col-reverse" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-secondary-foreground">
            <span class="hidden kt-menu-item-show:!flex">
             Show less
            </span>
            <span class="flex kt-menu-item-show:hidden">
             Show 3 more
            </span>
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Campaigns - Card
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Campaigns - List
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Empty
             </span>
            </a>
           </div>
          </div>
         </div>
        </div>
       </div>
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-setting-2 text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          My Account
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Account Home
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Get Started
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              User Profile
            </span>
           </a>
          </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Company Profile
            </span>
           </a>
          </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Settings - With Sidebar
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Settings - Enterprise
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Settings - Plain
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Settings - Modal
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Billing
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Billing - Basic
            </span>
           </a>
          </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $paymentSettingsUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Billing - Enterprise
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Plans
            </span>
           </a>
          </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $financeUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Billing History
            </span>
           </a>
          </div>
          </div>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Security
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Get Started
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Security Overview
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Allowed IP Addresses
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Privacy Settings
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Device Management
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Backup & Recovery
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Current Sessions
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Security Log
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Members & Roles
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Teams Starter
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Teams
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Team Info
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Members Starter
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Team Members
            </span>
           </a>
          </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="{{ $rolesUrl ?? '#' }}" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Import Members
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Roles
            </span>
           </a>
          </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Permissions - Toggler
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Permissions - Check
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Integrations
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Notifications
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            API Keys
           </span>
          </a>
         </div>
         <div class="kt-menu-item flex-col-reverse" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-secondary-foreground">
            <span class="hidden kt-menu-item-show:!flex">
             Show less
            </span>
            <span class="flex kt-menu-item-show:hidden">
             Show 3 more
            </span>
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Appearance
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Invite a Friend
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Activity
             </span>
            </a>
           </div>
          </div>
         </div>
        </div>
       </div>
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-users text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          Network
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Get Started
           </span>
          </a>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            User Cards
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Mini Cards
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Team Crew
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Author
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              NFT
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Social
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            User Table
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Team Crew
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              App Roster
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Market Authors
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              SaaS Users
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Store Clients
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Visitors
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item">
          <div class="kt-menu-label border border-transparent items-center grow gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground">
            Cooperations
           </span>
           <span class="kt-menu-badge me-[-10px]">
            <span class="kt-badge kt-badge-sm text-accent-foreground/60">
             Soon
            </span>
           </span>
          </div>
         </div>
         <div class="kt-menu-item">
          <div class="kt-menu-label border border-transparent items-center grow gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground">
            Leads
           </span>
           <span class="kt-menu-badge me-[-10px]">
            <span class="kt-badge kt-badge-sm text-accent-foreground/60">
             Soon
            </span>
           </span>
          </div>
         </div>
         <div class="kt-menu-item">
          <div class="kt-menu-label border border-transparent items-center grow gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground">
            Donators
           </span>
           <span class="kt-menu-badge me-[-10px]">
            <span class="kt-badge kt-badge-sm text-accent-foreground/60">
             Soon
            </span>
           </span>
          </div>
         </div>
        </div>
       </div>
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-security-user text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          Authentication
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Classic
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Sign In
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Sign Up
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              2FA
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Check Email
             </span>
            </a>
           </div>
           <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
            <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[5px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
              Reset Password
             </span>
             <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
              <span class="inline-flex kt-menu-item-show:hidden">
               <i class="ki-filled ki-plus text-[11px]">
               </i>
              </span>
              <span class="hidden kt-menu-item-show:inline-flex">
               <i class="ki-filled ki-minus text-[11px]">
               </i>
              </span>
             </span>
            </div>
            <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Enter Email
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Check Email
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Change Password
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Password is Changed
               </span>
              </a>
             </div>
            </div>
           </div>
          </div>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Branded
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Sign In
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Sign Up
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              2FA
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Check Email
             </span>
            </a>
           </div>
           <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
            <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[5px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
              Reset Password
             </span>
             <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
              <span class="inline-flex kt-menu-item-show:hidden">
               <i class="ki-filled ki-plus text-[11px]">
               </i>
              </span>
              <span class="hidden kt-menu-item-show:inline-flex">
               <i class="ki-filled ki-minus text-[11px]">
               </i>
              </span>
             </span>
            </div>
            <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Enter Email
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Check Email
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Change Password
               </span>
              </a>
             </div>
             <div class="kt-menu-item">
              <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
               <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
               </span>
               <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
                Password is Changed
               </span>
              </a>
             </div>
            </div>
           </div>
          </div>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Welcome Message
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Account Deactivated
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Error 404
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Error 500
           </span>
          </a>
         </div>
        </div>
       </div>
       <div class="kt-menu-item pt-2.25 pb-px">
        <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">
         Apps
        </span>
       </div>
       <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
        <div class="kt-menu-link flex items-center grow cursor-pointer border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-users text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">
          Store - Client
         </span>
         <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
          <span class="inline-flex kt-menu-item-show:hidden">
           <i class="ki-filled ki-plus text-[11px]">
           </i>
          </span>
          <span class="hidden kt-menu-item-show:inline-flex">
           <i class="ki-filled ki-minus text-[11px]">
           </i>
          </span>
         </span>
        </div>
        <div class="kt-menu-accordion gap-1 ps-[10px] relative before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Home
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Search Results - Grid
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Search Results - List
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Product Details
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Shopping Cart
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Wishlist
           </span>
          </a>
         </div>
         <div class="kt-menu-item" data-kt-menu-item-toggle="accordion" data-kt-menu-item-trigger="click">
          <div class="kt-menu-link border border-transparent grow cursor-pointer gap-[14px] ps-[10px] pe-[10px] py-[8px]" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal me-1 text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-medium kt-menu-link-hover:!text-primary">
            Checkout
           </span>
           <span class="kt-menu-arrow text-muted-foreground w-[20px] shrink-0 justify-end ms-1 me-[-10px]">
            <span class="inline-flex kt-menu-item-show:hidden">
             <i class="ki-filled ki-plus text-[11px]">
             </i>
            </span>
            <span class="hidden kt-menu-item-show:inline-flex">
             <i class="ki-filled ki-minus text-[11px]">
             </i>
            </span>
           </span>
          </div>
          <div class="kt-menu-accordion gap-1 relative before:absolute before:start-[32px] ps-[22px] before:top-0 before:bottom-0 before:border-s before:border-border">
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Order Summary
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Shipping Info
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Payment Method
             </span>
            </a>
           </div>
           <div class="kt-menu-item">
            <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[5px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
             <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
             </span>
             <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
              Order Placed
             </span>
            </a>
           </div>
          </div>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            My Orders
           </span>
          </a>
         </div>
         <div class="kt-menu-item">
          <a class="kt-menu-link border border-transparent items-center grow kt-menu-item-active:bg-accent/60 dark:menu-item-active:border-border kt-menu-item-active:rounded-lg hover:bg-accent/60 hover:rounded-lg gap-[14px] ps-[10px] pe-[10px] py-[8px]" href="#" tabindex="0">
           <span class="kt-menu-bullet flex w-[6px] -start-[3px] rtl:start-0 relative before:absolute before:top-0 before:size-[6px] before:rounded-full rtl:before:translate-x-1/2 before:-translate-y-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary">
           </span>
           <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:text-primary kt-menu-item-active:font-semibold kt-menu-link-hover:!text-primary">
            Order Receipt
           </span>
          </a>
         </div>
        </div>
       </div>
       <div class="kt-menu-item">
        <div class="kt-menu-label border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-setting text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground">
          Store - Admin
         </span>
         <span class="kt-menu-badge me-[-10px]">
          <span class="kt-badge kt-badge-sm text-accent-foreground/60">
           Soon
          </span>
         </span>
        </div>
       </div>
       <div class="kt-menu-item">
        <div class="kt-menu-label border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-python text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground">
          Store - Services
         </span>
         <span class="kt-menu-badge me-[-10px]">
          <span class="kt-badge kt-badge-sm text-accent-foreground/60">
           Soon
          </span>
         </span>
        </div>
       </div>
       <div class="kt-menu-item">
        <div class="kt-menu-label border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-artificial-intelligence text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground">
          AI Promt
         </span>
         <span class="kt-menu-badge me-[-10px]">
          <span class="kt-badge kt-badge-sm text-accent-foreground/60">
           Soon
          </span>
         </span>
        </div>
       </div>
       <div class="kt-menu-item">
        <div class="kt-menu-label border border-transparent gap-[10px] ps-[10px] pe-[10px] py-[6px]" href="#" tabindex="0">
         <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
          <i class="ki-filled ki-cheque text-lg">
          </i>
         </span>
         <span class="kt-menu-title text-sm font-medium text-foreground">
          Invoice Generator
         </span>
         <span class="kt-menu-badge me-[-10px]">
          <span class="kt-badge kt-badge-sm text-accent-foreground/60">
           Soon
          </span>
         </span>
        </div>
       </div>
      </div>
      <!-- End of Sidebar Menu -->
            </div>
        </div>
    </div>

    <div class="kt-wrapper flex grow flex-col">
        <header class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background" data-kt-sticky="true" data-kt-sticky-class="border-b border-border" data-kt-sticky-name="header" id="header">
            <div class="kt-container-fixed flex justify-between items-stretch lg:gap-4" id="headerContainer">
                <div class="flex gap-2.5 lg:hidden items-center -ms-1">
                    <a class="shrink-0" href="{{ $homeUrl }}">
                        <img class="max-h-[25px] w-full" src="{{ $miniLogo }}" alt="Logo"/>
                    </a>
                    <div class="flex items-center">
                        <button class="kt-btn kt-btn-icon kt-btn-ghost" data-kt-drawer-toggle="#sidebar">
                            <i class="ki-filled ki-menu"></i>
                        </button>
                        <button class="kt-btn kt-btn-icon kt-btn-ghost" data-kt-drawer-toggle="#mega_menu_wrapper">
                            <i class="ki-filled ki-burger-menu-2"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-stretch" id="megaMenuContainer">
                    <div class="flex items-stretch [--kt-reparent-mode:prepend] [--kt-reparent-target:body] lg:[--kt-reparent-target:#megaMenuContainer] lg:[--kt-reparent-mode:prepend]" data-kt-reparent="true">
                        <div class="hidden lg:flex lg:items-stretch [--kt-drawer-enable:true] lg:[--kt-drawer-enable:false]"
                             data-kt-drawer="true"
                             data-kt-drawer-class="kt-drawer kt-drawer-start fixed z-10 top-0 bottom-0 w-full me-5 max-w-[250px] p-5 lg:p-0 overflow-auto"
                             id="mega_menu_wrapper">
                            <div class="kt-menu flex-col lg:flex-row gap-5 lg:gap-7.5" data-kt-menu="true" id="mega_menu">
          <!--Megamenu Item-->
          <div class="kt-menu-item active">
           <a class="kt-menu-link text-nowrap text-sm text-foreground font-medium kt-menu-item-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-active:font-medium" href="{{ $homeUrl }}">
            <span class="kt-menu-title text-nowrap">
             Home
            </span>
           </a>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="accordion|lg:dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-foreground kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-medium kt-menu-item-here:font-medium">
            <span class="kt-menu-title text-nowrap">
             Workspace
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown w-full gap-0 lg:max-w-[960px]">
            <div class="pt-4 pb-2 lg:p-7.5">
             <div class="grid lg:grid-cols-3 gap-5 lg:gap-7.5">
              @if ($showSuperadminLinks)
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                 Superadmin
                </h3>
                @foreach ($superadminNavLinks as $navLink)
                 @if ($navLink['url'])
                  <div class="kt-menu-item">
                   <a class="kt-menu-link" href="{{ $navLink['url'] }}" tabindex="0">
                    <span class="kt-menu-title grow-0">
                     {{ $navLink['label'] }}
                    </span>
                   </a>
                  </div>
                 @endif
                @endforeach
               </div>
              @endif
              @if ($isTenantContext)
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                 Tenant
                </h3>
                @foreach ($tenantNavLinks as $navLink)
                 @if ($navLink['url'])
                  <div class="kt-menu-item">
                   <a class="kt-menu-link" href="{{ $navLink['url'] }}" tabindex="0">
                    <span class="kt-menu-title grow-0">
                     {{ $navLink['label'] }}
                    </span>
                   </a>
                  </div>
                 @endif
                @endforeach
               </div>
              @endif
              <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Modules
               </h3>
               @foreach ($moduleNavLinks as $moduleLink)
                @if ($moduleLink['url'])
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="{{ $moduleLink['url'] }}" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    {{ $moduleLink['label'] }}
                   </span>
                  </a>
                 </div>
                @endif
               @endforeach
              </div>
             </div>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="accordion|lg:dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-foreground kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-medium kt-menu-item-here:font-medium">
            <span class="kt-menu-title text-nowrap">
             Profiles
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown w-full gap-0 lg:max-w-[900px]">
            <div class="pt-4 pb-2 lg:p-7.5">
             <div class="grid lg:grid-cols-2 gap-5 lg:gap-10">
              <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Profiles
               </h3>
               <div class="grid lg:grid-cols-2 lg:gap-5">
                <div class="flex flex-col gap-0.5">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-badge">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Default
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-coffee">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Creator
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-abstract-41">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Company
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-bitcoin">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    NFT
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-message-text">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Blogger
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-devices">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    CRM
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-ghost">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Gamer
                   </span>
                  </a>
                 </div>
                </div>
                <div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-book">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Feeds
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-files">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Plain
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-mouse-square">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Modal
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-financial-schedule">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Freelancer
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-technology-4">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Developer
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-users">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Team
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-calendar-tick">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Events
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                </div>
               </div>
              </div>
              <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Other Pages
               </h3>
               <div class="grid lg:grid-cols-2 lg:gap-5">
                <div class="flex flex-col gap-0.5">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-element-6">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Projects - 3 Columns
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-element-4">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Projects - 2 Columns
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-office-bag">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Works
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-people">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Teams
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-icon">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Network
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-chart-line-up-2">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Activity
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-element-11">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Campaigns - Card
                   </span>
                  </a>
                 </div>
                </div>
                <div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-kanban">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Campaigns - List
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-file-sheet">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Empty Page
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-document">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Documents
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-award">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Badges
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-gift">
                    </i>
                   </span>
                   <span class="kt-menu-title grow-0">
                    Awards
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                </div>
               </div>
              </div>
             </div>
            </div>
            <div class="flex flex-wrap items-center lg:justify-between rounded-xl lg:rounded-t-none border border-border lg:border-0 lg:border-t lg:border-t-border px-4 py-4 lg:px-7.5 lg:py-5 gap-2.5 bg-muted/50">
             <div class="flex flex-col gap-1.5">
              <div class="text-base font-semibold text-mono leading-none">
               Read to Get Started ?
              </div>
              <div class="text-sm fomt-medium text-secondary-foreground">
               Take your docs to the next level of Metronic
              </div>
             </div>
             <a class="kt-btn kt-btn-mono" href="#">
              Read Documentation
             </a>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-offset="-300px, 0" data-kt-menu-item-offset-rtl="300px, 0" data-kt-menu-item-overflow="true" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="accordion|lg:dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-secondary-foreground font-medium kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-semibold kt-menu-item-here:font-semibold">
            <span class="kt-menu-title text-nowrap">
             My Account
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown flex-col lg:flex-row gap-0 w-full lg:max-w-[1240px]">
            <div class="lg:w-[250px] mt-2 lg:mt-0 lg:border-e lg:border-e-border rounded-xl lg:rounded-l-xl lg:rounded-r-none shrink-0 px-3 py-4 lg:p-7.5 bg-muted/25">
             <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
              General Pages
             </h3>
             <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-technology-2">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Integrations
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-notification-1">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Notifications
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-key">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 API Keys
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-eye">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Appearance
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-user-tick">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Invite a Friend
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-support">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Activity
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-verify">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Brand
                </span>
                <span class="kt-menu-badge">
                 <span class="kt-badge kt-badge-sm">
                  Soon
                 </span>
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-euro">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Get Paid
                </span>
                <span class="kt-menu-badge">
                 <span class="kt-badge kt-badge-sm">
                  Soon
                 </span>
                </span>
               </a>
              </div>
             </div>
            </div>
            <div class="pt-4 pb-2 lg:p-7.5 lg:pb-5 grow">
             <div class="grid lg:grid-cols-5 gap-5">
              <div class="flex flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Account Home
               </h3>
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Get Started
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $profileUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   User Profile
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $settingsUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Company Profile
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   With Sidebar
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Enterprise
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Plain
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Modal
                  </span>
                 </a>
                </div>
               </div>
              </div>
              <div class="flex flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Billing
               </h3>
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $billingSettingsUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Basic Billing
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Enterprise
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $paymentSettingsUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Plans
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $financeUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Billing History
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Tax Info
                  </span>
                  <span class="kt-menu-badge">
                   <span class="kt-badge kt-badge-sm">
                    Soon
                   </span>
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Invoices
                  </span>
                  <span class="kt-menu-badge">
                   <span class="kt-badge kt-badge-sm">
                    Soon
                   </span>
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Gateaways
                  </span>
                  <span class="kt-menu-badge">
                   <span class="kt-badge kt-badge-sm">
                    Soon
                   </span>
                  </span>
                 </a>
                </div>
               </div>
              </div>
              <div class="flex flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Security
               </h3>
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Get Started
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Security Overview
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   IP Addresses
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Privacy Settings
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Device Management
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Backup & Recovery
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Current Sessions
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Security Log
                  </span>
                 </a>
                </div>
               </div>
              </div>
              <div class="flex flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Members & Roles
               </h3>
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Teams Starter
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Teams
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Team Info
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Members Starter
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $usersUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Team Members
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Import Members
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="{{ $rolesUrl ?? '#' }}" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Roles
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Persmissions - Toggler
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Persmissions - Check
                  </span>
                 </a>
                </div>
               </div>
              </div>
              <div class="flex flex-col">
               <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                Other Pages
               </h3>
               <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Integrations
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Notifications
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   API Keys
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Appearance
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Invite a Friend
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-title grow-0">
                   Activity
                  </span>
                 </a>
                </div>
               </div>
              </div>
             </div>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-offset="-300px, 0" data-kt-menu-item-offset-rtl="300px, 0" data-kt-menu-item-overflow="true" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="accordion|lg:dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-secondary-foreground font-medium kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-semibold kt-menu-item-here:font-semibold">
            <span class="kt-menu-title text-nowrap">
             Network
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown flex-col gap-0 w-full lg:max-w-[670px]">
            <div class="flex flex-col lg:flex-row">
             <div class="flex flex-col gap-5 lg:w-[250px] mt-2 lg:mt-0 lg:border-r lg:border-r-border rounded-xl lg:rounded-none lg:rounded-tl-xl shrink-0 px-3 py-4 lg:p-7.5 bg-muted/25">
              <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 h-3.5">
               General Pages
              </h3>
              <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-flag">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Get Started
                 </span>
                </a>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-users">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Colleagues
                 </span>
                 <span class="kt-menu-badge">
                  <span class="kt-badge kt-badge-sm">
                   Soon
                  </span>
                 </span>
                </a>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-heart">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Donators
                 </span>
                 <span class="kt-menu-badge">
                  <span class="kt-badge kt-badge-sm">
                   Soon
                  </span>
                 </span>
                </a>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-abstract-21">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Leads
                 </span>
                 <span class="kt-menu-badge">
                  <span class="kt-badge kt-badge-sm">
                   Soon
                  </span>
                 </span>
                </a>
               </div>
              </div>
             </div>
             <div class="pt-4 pb-2 lg:p-7.5 lg:pb-5 grow">
              <div class="grid lg:grid-cols-2 gap-5">
               <div class="flex flex-col gap-5">
                <h3 class="flex items-center gap-1.5 text-sm text-foreground font-semibold leading-none ps-2.5 h-3.5">
                 User Cards
                </h3>
                <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Mini Cards
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Team Crew
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Authors
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    NFT Users
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Social Users
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Gamers
                   </span>
                   <span class="kt-menu-badge">
                    <span class="kt-badge kt-badge-sm">
                     Soon
                    </span>
                   </span>
                  </a>
                 </div>
                </div>
               </div>
               <div class="flex flex-col gap-5">
                <h3 class="flex items-center gap-1.5 text-sm text-foreground font-semibold leading-none ps-2.5 h-3.5">
                 User Base
                 <span class="left-auto kt-badge kt-badge-sm kt-badge-primary kt-badge-outline">
                  Datatables
                 </span>
                </h3>
                <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Team Crew
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    App Roster
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Market Authors
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    SaaS Users
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Store Clients
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Visitors
                   </span>
                  </a>
                 </div>
                </div>
               </div>
              </div>
             </div>
            </div>
            <div class="flex flex-wrap items-center lg:justify-between rounded-xl lg:rounded-t-none border border-border lg:border-0 lg:border-t lg:border-t-border px-4 py-4 lg:px-7.5 lg:py-5 gap-2.5 bg-muted/50">
             <div class="flex flex-col gap-1.5">
              <div class="text-base font-semibold text-mono leading-none">
               Read to Get Started ?
              </div>
              <div class="text-sm fomt-medium text-secondary-foreground">
               Take your docs to the next level of Metronic
              </div>
             </div>
             <a class="kt-btn kt-btn-mono" href="#">
              Read Documentation
             </a>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-offset="-300px, 0" data-kt-menu-item-offset-rtl="300px, 0" data-kt-menu-item-overflow="true" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="accordion|lg:dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-secondary-foreground font-medium kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-semibold kt-menu-item-here:font-semibold">
            <span class="kt-menu-title text-nowrap">
             Store
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown w-full gap-0 lg:max-w-[600px]">
            <div class="pt-4 pb-2 lg:p-7.5">
             <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
              <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
               Store - Client
              </h3>
              <div class="grid lg:grid-cols-2 lg:gap-5">
               <div class="flex flex-col gap-0.5">
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-home">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Home
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-grid">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Search Results - Grid
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-tablet-text-up">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Search Results - List
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-picture">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Product Details
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-handcart">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Shopping Cart
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-heart">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Wishlist
                  </span>
                 </a>
                </div>
               </div>
               <div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-subtitle">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Checkout - Order Summary
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-delivery">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Checkout - Shipping Info
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-wallet">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Checkout - Payment Method
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-check-circle">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Checkout - Order Placed
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-archive">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   My Orders
                  </span>
                 </a>
                </div>
                <div class="kt-menu-item">
                 <a class="kt-menu-link" href="#" tabindex="0">
                  <span class="kt-menu-icon">
                   <i class="ki-filled ki-document">
                   </i>
                  </span>
                  <span class="kt-menu-title grow-0">
                   Order Receipt
                  </span>
                 </a>
                </div>
               </div>
              </div>
             </div>
            </div>
            <div class="flex flex-wrap items-center lg:justify-between rounded-xl lg:rounded-t-none border border-border lg:border-0 lg:border-t lg:border-t-border px-4 py-4 lg:px-7.5 lg:py-5 gap-2.5 bg-muted/50">
             <div class="flex flex-col gap-1.5">
              <div class="text-base font-semibold text-mono leading-none">
               Read to Get Started ?
              </div>
              <div class="text-sm fomt-medium text-secondary-foreground">
               Take your docs to the next level of Metronic
              </div>
             </div>
             <a class="kt-btn kt-btn-mono" href="#">
              Read Documentation
             </a>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-offset="-300px, 0" data-kt-menu-item-offset-rtl="300px, 0" data-kt-menu-item-overflow="true" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="accordion|lg:dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-secondary-foreground font-medium kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-semibold kt-menu-item-here:font-semibold">
            <span class="kt-menu-title text-nowrap">
             Authentication
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown flex-col gap-0 w-full lg:max-w-[700px]">
            <div class="flex flex-col lg:flex-row">
             <div class="pt-4 pb-2 lg:p-7.5 lg:pb-5 grow">
              <div class="grid lg:grid-cols-2 gap-5">
               <div class="flex flex-col">
                <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                 Classic Layout
                </h3>
                <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Sign In
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Sign Up
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    2FA
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Check Email
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item pt-1">
                  <span class="text-secondary-foreground font-medium text-sm p-2.5">
                   Reset Password
                  </span>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Enter Email
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Check Email
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Change Password
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Password is Changed
                   </span>
                  </a>
                 </div>
                </div>
               </div>
               <div class="flex flex-col">
                <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-2 lg:mb-5">
                 Branded Layout
                </h3>
                <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Sign In
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Sign Up
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    2FA
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Check Email
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item pt-1">
                  <span class="text-secondary-foreground font-medium text-sm p-2.5">
                   Reset Password
                  </span>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Enter Email
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Check Email
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Change Password
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#" tabindex="0">
                   <span class="kt-menu-title grow-0">
                    Password is Changed
                   </span>
                  </a>
                 </div>
                </div>
               </div>
              </div>
             </div>
             <div class="lg:w-[260px] mb-4 lg:mb-0 lg:border-s lg:border-s-border rounded-xl lg:rounded-e-xl lg:rounded-l-none shrink-0 px-3 py-4 lg:p-7.5 bg-muted/25">
              <h3 class="text-sm text-foreground font-semibold leading-none ps-2.5 mb-5">
               Other Pages
              </h3>
              <div class="kt-menu kt-menu-default kt-menu-fit flex-col">
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-like-2">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Welcome Message
                 </span>
                </a>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-shield-cross">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Account Deactivated
                 </span>
                </a>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-message-question">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Error 404
                 </span>
                </a>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link border border-transparent kt-menu-link-hover:!bg-background kt-menu-link-hover:border-border kt-menu-item-active:!bg-background kt-menu-item-active:border-border" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-information">
                  </i>
                 </span>
                 <span class="kt-menu-title grow-0">
                  Error 500
                 </span>
                </a>
               </div>
              </div>
             </div>
            </div>
            <div class="flex flex-wrap items-center lg:justify-between rounded-xl lg:rounded-t-none border border-border lg:border-0 lg:border-t lg:border-t-border px-4 py-4 lg:px-7.5 lg:py-5 gap-2.5 bg-muted/50">
             <div class="flex flex-col gap-1.5">
              <div class="text-base font-semibold text-mono leading-none">
               Read to Get Started ?
              </div>
              <div class="text-sm fomt-medium text-secondary-foreground">
               Take your docs to the next level of Metronic
              </div>
             </div>
             <a class="kt-btn kt-btn-mono" href="#">
              Read Documentation
             </a>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
          <!--Megamenu Item-->
          <div class="kt-menu-item" data-kt-menu-item-offset="0,0|lg:-20px, 0" data-kt-menu-item-offset-rtl="0,0|lg:20px, 0" data-kt-menu-item-overflow="true" data-kt-menu-item-placement="bottom-start" data-kt-menu-item-placement-rtl="bottom-end" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <div class="kt-menu-link text-sm text-secondary-foreground font-medium kt-menu-link-hover:text-primary kt-menu-item-active:text-mono kt-menu-item-show:text-primary kt-menu-item-here:text-mono kt-menu-item-active:font-semibold kt-menu-item-here:font-semibold">
            <span class="kt-menu-title text-nowrap">
             Help
            </span>
            <span class="kt-menu-arrow flex lg:hidden">
             <span class="kt-menu-item-show:hidden text-muted-foreground">
              <i class="ki-filled ki-plus text-xs">
              </i>
             </span>
             <span class="hidden kt-menu-item-show:inline-flex">
              <i class="ki-filled ki-minus text-xs">
              </i>
             </span>
            </span>
           </div>
           <div class="kt-menu-dropdown kt-menu-default py-2.5 w-full max-w-[220px]">
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#" tabindex="0">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-coffee">
               </i>
              </span>
              <span class="kt-menu-title grow-0">
               Getting Started
              </span>
             </a>
            </div>
            <div class="kt-menu-item" data-kt-menu-item-placement="right-start" data-kt-menu-item-placement-rtl="left-start" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click|lg:hover">
             <div class="kt-menu-link">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-information">
               </i>
              </span>
              <span class="kt-menu-title">
               Support Forum
              </span>
              <span class="kt-menu-arrow">
               <i class="ki-filled ki-right text-xs rtl:transform rtl:rotate-180">
               </i>
              </span>
             </div>
             <div class="kt-menu-dropdown kt-menu-default w-full max-w-[175px] lg:max-w-[220px]">
              <div class="kt-menu-item">
               <a class="kt-menu-link" href="#" tabindex="0">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-questionnaire-tablet">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 All Questions
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link" href="#" tabindex="0">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-star">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Popular Questions
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link" href="#" tabindex="0">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-message-question">
                 </i>
                </span>
                <span class="kt-menu-title grow-0">
                 Ask Question
                </span>
               </a>
              </div>
             </div>
            </div>
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#" tabindex="0">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-subtitle">
               </i>
              </span>
              <span class="kt-menu-title">
               Licenses & FAQ
              </span>
              <span class="kt-menu-badge" data-kt-tooltip="#menu_tooltip_3">
               <i class="ki-filled ki-information-2 text-muted-foreground text-base">
               </i>
              </span>
              <div class="kt-tooltip" id="menu_tooltip_3">
               Learn more about licenses
              </div>
             </a>
            </div>
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#" tabindex="0">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-questionnaire-tablet">
               </i>
              </span>
              <span class="kt-menu-title grow-0">
               Documentation
              </span>
             </a>
            </div>
            <div class="kt-menu-separator">
            </div>
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#" tabindex="0">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-share">
               </i>
              </span>
              <span class="kt-menu-title grow-0">
               Contact Us
              </span>
             </a>
            </div>
           </div>
          </div>
          <!--End of Megamenu Item-->
         </div>
         <!--End of Megamenu-->
                        </div>
                    </div>
                </div>

                <!-- Topbar -->
      <div class="flex items-center gap-2.5">
       <!-- Search -->
       <button class="group kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-modal-toggle="#search_modal">
        <i class="ki-filled ki-magnifier text-lg group-hover:text-primary">
        </i>
       </button>
       <!-- End of Search -->
       <!-- Notifications -->
       <button class="kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-drawer-toggle="#notifications_drawer">
        <i class="ki-filled ki-notification-status text-lg">
        </i>
       </button>
       <!--Notifications Drawer-->
       <div class="hidden kt-drawer kt-drawer-end card flex-col max-w-[90%] w-[450px] top-5 bottom-5 end-5 rounded-xl border border-border" data-kt-drawer="true" data-kt-drawer-container="body" id="notifications_drawer">
        <div class="flex items-center justify-between gap-2.5 text-sm text-mono font-semibold px-5 py-2.5 border-b border-b-border" id="notifications_header">
         Notifications
         <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-dim shrink-0" data-kt-drawer-dismiss="true">
          <i class="ki-filled ki-cross">
          </i>
         </button>
        </div>
        <div class="kt-tabs kt-tabs-line justify-between px-5 mb-2" data-kt-tabs="true" id="notifications_tabs">
         <div class="flex items-center gap-5">
          <button class="kt-tab-toggle py-3 active" data-kt-tab-toggle="#notifications_tab_all">
           All
          </button>
          <button class="kt-tab-toggle py-3 relative" data-kt-tab-toggle="#notifications_tab_inbox">
           Inbox
           <span class="rounded-full bg-green-500 size-[5px] absolute top-2 rtl:start-0 end-0 transform translate-y-1/2 translate-x-full">
           </span>
          </button>
          <button class="kt-tab-toggle py-3" data-kt-tab-toggle="#notifications_tab_team">
           Team
          </button>
          <button class="kt-tab-toggle py-3" data-kt-tab-toggle="#notifications_tab_following">
           Following
          </button>
         </div>
         <div class="kt-menu" data-kt-menu="true">
          <div class="kt-menu-item" data-kt-menu-item-offset="0,10px" data-kt-menu-item-placement="bottom-end" data-kt-menu-item-placement-rtl="bottom-start" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click|lg:hover">
           <button class="kt-menu-toggle kt-btn kt-btn-icon kt-btn-ghost">
            <i class="ki-filled ki-setting-2">
            </i>
           </button>
           <div class="kt-menu-dropdown kt-menu-default w-full max-w-[175px]" data-kt-menu-dismiss="true">
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-document">
               </i>
              </span>
              <span class="kt-menu-title">
               View
              </span>
             </a>
            </div>
            <div class="kt-menu-item" data-kt-menu-item-offset="-15px, 0" data-kt-menu-item-placement="right-start" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click|lg:hover">
             <div class="kt-menu-link">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-notification-status">
               </i>
              </span>
              <span class="kt-menu-title">
               Export
              </span>
              <span class="kt-menu-arrow">
               <i class="ki-filled ki-right text-xs rtl:transform rtl:rotate-180">
               </i>
              </span>
             </div>
             <div class="kt-menu-dropdown kt-menu-default w-full max-w-[175px]">
              <div class="kt-menu-item">
               <a class="kt-menu-link" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-sms">
                 </i>
                </span>
                <span class="kt-menu-title">
                 Email
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-message-notify">
                 </i>
                </span>
                <span class="kt-menu-title">
                 SMS
                </span>
               </a>
              </div>
              <div class="kt-menu-item">
               <a class="kt-menu-link" href="#">
                <span class="kt-menu-icon">
                 <i class="ki-filled ki-notification-status">
                 </i>
                </span>
                <span class="kt-menu-title">
                 Push
                </span>
               </a>
              </div>
             </div>
            </div>
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-pencil">
               </i>
              </span>
              <span class="kt-menu-title">
               Edit
              </span>
             </a>
            </div>
            <div class="kt-menu-item">
             <a class="kt-menu-link" href="#">
              <span class="kt-menu-icon">
               <i class="ki-filled ki-trash">
               </i>
              </span>
              <span class="kt-menu-title">
               Delete
              </span>
             </a>
            </div>
           </div>
          </div>
         </div>
        </div>
        <div class="grow flex flex-col" id="notifications_tab_all">
         <div class="grow kt-scrollable-y-auto" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="150px">
          <div class="grow flex flex-col gap-5 pt-3 pb-4 divider-y divider-border">
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-4.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Joe Lincoln
               </a>
               <span class="text-secondary-foreground">
                mentioned you in
               </span>
               <a class="hover:text-primary text-primary" href="#">
                Latest Trends
               </a>
               <span class="text-secondary-foreground">
                topic
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               18 mins ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Web Design 2024
              </span>
             </div>
             <div class="kt-card shadow-none flex flex-col gap-2.5 p-3.5 rounded-lg bg-muted/70">
              <div class="text-sm font-semibold text-secondary-foreground mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                @Cody
               </a>
               <span class="text-secondary-foreground font-medium">
                For an expert opinion, check out what Mike has to say on this topic!
               </span>
              </div>
              <div class="kt-input">
               <input placeholder="Reply" type="text" value=""/>
               <button class="kt-btn kt-btn-ghost kt-btn-icon size-6 -me-1.5">
                <i class="ki-filled ki-picture">
                </i>
               </button>
              </div>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-5.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Leslie Alexander
               </a>
               <span class="text-secondary-foreground">
                added new tags to
               </span>
               <a class="hover:text-primary text-primary" href="#">
                Web Redesign 2024
               </a>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               53 mins ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               ACME
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <span class="kt-badge kt-badge-sm kt-badge-info kt-badge-outline">
               Client-Request
              </span>
              <span class="kt-badge kt-badge-sm kt-badge-warning kt-badge-outline">
               Figma
              </span>
              <span class="kt-badge kt-badge-sm kt-badge-secondary kt-badge-outline">
               Redesign
              </span>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5" id="notification_request_3">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-27.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Guy Hawkins
               </a>
               <span class="text-secondary-foreground">
                requested access to
               </span>
               <a class="hover:text-primary text-primary" href="#">
                AirSpace
               </a>
               <span class="text-secondary-foreground">
                project
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               14 hours ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Dev Team
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Accept
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-10.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-offline size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5 grow">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Jane Perez
               </a>
               <span class="text-secondary-foreground">
                invites you to review a file.
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 hours ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               742kb
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center flex-row gap-1.5 p-2.5 rounded-lg bg-muted/70">
              <img class="h-5" src="{{ asset('assets/metronic/media/file-types/pdf.svg') }}"/>
              <a class="hover:text-primary font-medium text-secondary-foreground text-xs me-1" href="#">
               Launch_nov24.pptx
              </a>
              <span class="font-medium text-muted-foreground text-xs">
               Edited 39 mins ago
              </span>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-11.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-1">
             <div class="text-sm font-medium mb-px">
              <a class="hover:text-primary text-mono font-semibold" href="#">
               Raymond Pawell
              </a>
              <span class="text-secondary-foreground">
               posted a new article
              </span>
              <a class="hover:text-primary text-primary" href="#">
               2024 Roadmap
              </a>
             </div>
             <span class="flex items-center text-xs font-medium text-muted-foreground">
              1 hour ago
              <span class="rounded-full size-1 bg-mono/30 mx-1.5">
              </span>
              Roadmap
             </span>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-14.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-offline size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5 grow">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Tyler Hero
               </a>
               <span class="text-secondary-foreground">
                wants to view your design project
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 day ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Metronic Launcher mockups
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center flex-row gap-1.5 p-2.5 rounded-lg bg-muted/70">
              <div class="flex items-center justify-center w-[26px] h-[30px] shrink-0 bg-background rounded-sm border border-border">
               <img class="h-5" src="{{ asset('assets/metronic/media/file-types/figma.svg') }}"/>
              </div>
              <a class="hover:text-primary font-medium text-secondary-foreground text-xs me-1" href="#">
               Launcher-UIkit.fig
              </a>
              <span class="font-medium text-muted-foreground text-xs">
               Edited 2 mins ago
              </span>
             </div>
            </div>
           </div>
          </div>
         </div>
         <div class="border-b border-b-border">
         </div>
         <div class="grid grid-cols-2 p-5 gap-2.5" id="notifications_all_footer">
          <button class="kt-btn kt-btn-outline justify-center">
           Archive all
          </button>
          <button class="kt-btn kt-btn-outline justify-center">
           Mark all as read
          </button>
         </div>
        </div>
        <div class="grow flex flex-col hidden" id="notifications_tab_inbox">
         <div class="grow kt-scrollable-y-auto" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="150px">
          <div class="flex flex-col gap-5 pt-3 pb-4">
           <div class="flex grow gap-2.5 px-5" id="notification_request_13">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-25.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5 grow">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Samuel Lee
               </a>
               <span class="text-secondary-foreground">
                requested to add user to
               </span>
               <a class="hover:text-primary text-primary font-semibold" href="#">
                TechSynergy
               </a>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               22 hours ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Dev Team
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center flex-row justify-between gap-1.5 px-2.5 py-2 rounded-lg bg-muted/70">
              <div class="flex flex-col">
               <a class="hover:text-primary font-medium text-mono text-xs" href="#">
                Ronald Richards
               </a>
               <a class="hover:text-primary text-muted-foreground font-medium text-xs" href="#">
                ronald.richards@gmail.com
               </a>
              </div>
              <a class="hover:text-primary text-secondary-foreground font-medium text-xs" href="#">
               Go to profile
              </a>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_13">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_13">
               Accept
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex items-center grow gap-2.5 px-5">
            <div class="flex items-center justify-center size-8 bg-green-50 rounded-full border border-green-200 dark:border-green-950">
             <i class="ki-filled ki-check text-lg text-green-500">
             </i>
            </div>
            <div class="flex flex-col gap-1">
             <span class="text-sm font-medium text-secondary-foreground">
              You have succesfully verified your account
             </span>
             <span class="font-medium text-muted-foreground text-xs">
              2 days ago
             </span>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-34.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5 grow">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Ava Peterson
               </a>
               <span class="text-secondary-foreground">
                uploaded attachment
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               ACME
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center justify-between flex-row gap-1.5 p-2.5 rounded-lg bg-muted/70">
              <div class="flex items-center gap-1.5">
               <img class="h-6" src="{{ asset('assets/metronic/media/file-types/xls.svg') }}"/>
               <div class="flex flex-col gap-0.5">
                <a class="hover:text-primary font-medium text-secondary-foreground text-xs" href="#">
                 Redesign-2024.xls
                </a>
                <span class="font-medium text-muted-foreground text-xs">
                 2.6 MB
                </span>
               </div>
              </div>
              <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost">
               <svg fill="none" height="14" viewbox="0 0 14 14" width="14" xmlns="http://www.w3.org/2000/svg">
                <path clip-rule="evenodd" d="M6.63821 2.60467C4.81926 2.60467 3.32474 3.99623 3.16201 5.77252C3.1386 6.02803 2.92413 6.22253 2.66871 6.22227C1.74915 6.22149 0.976744 6.9868 0.976744 7.90442C0.976744 8.83344 1.72988 9.58657 2.65891 9.58657H3.09302C3.36274 9.58657 3.5814 9.80523 3.5814 10.0749C3.5814 10.3447 3.36274 10.5633 3.09302 10.5633H2.65891C1.19044 10.5633 0 9.37292 0 7.90442C0 6.58614 0.986948 5.48438 2.24496 5.27965C2.62863 3.20165 4.44941 1.62793 6.63821 1.62793C8.26781 1.62793 9.69282 2.50042 10.4729 3.80193C12.3411 3.72829 14 5.2564 14 7.18091C14 8.93508 12.665 10.3769 10.9552 10.5466C10.6868 10.5733 10.4476 10.3773 10.421 10.1089C10.3943 9.84052 10.5903 9.60135 10.8587 9.57465C12.0739 9.45406 13.0233 8.42802 13.0233 7.18091C13.0233 5.74002 11.6905 4.59666 10.2728 4.79968C10.0642 4.82957 9.85672 4.72382 9.76028 4.53181C9.18608 3.38796 8.00318 2.60467 6.63821 2.60467Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
                <path clip-rule="evenodd" d="M6.99909 8.01611L8.28162 9.29864C8.47235 9.48937 8.78158 9.48937 8.97231 9.29864C9.16303 9.10792 9.16303 8.79874 8.97231 8.60802L7.57465 7.2103C7.25675 6.89247 6.74143 6.89247 6.42353 7.2103L5.02585 8.60802C4.83513 8.79874 4.83513 9.10792 5.02585 9.29864C5.21657 9.48937 5.5258 9.48937 5.71649 9.29864L6.99909 8.01611Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
                <path clip-rule="evenodd" d="M7.00009 12.372C7.2698 12.372 7.48846 12.1533 7.48846 11.8836V7.97665C7.48846 7.70694 7.2698 7.48828 7.00009 7.48828C6.73038 7.48828 6.51172 7.70694 6.51172 7.97665V11.8836C6.51172 12.1533 6.73038 12.372 7.00009 12.372Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
               </svg>
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-29.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3 grow">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Ethan Parker
               </a>
               <span class="text-secondary-foreground">
                created a new tasks to
               </span>
               <a class="hover:text-primary text-primary" href="#">
                Site Sculpt
               </a>
               <span class="text-secondary-foreground">
                project
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Web Designer
              </span>
             </div>
             <div class="kt-card shadow-none p-3.5 gap-3.5 rounded-lg bg-muted/70">
              <div class="flex items-center justify-between flex-wrap gap-2.5">
               <div class="flex flex-col gap-1">
                <span class="font-medium text-mono text-xs">
                 Location history is erased after Logging In
                </span>
                <span class="font-medium text-muted-foreground text-xs">
                 Due Date: 15 May, 2024
                </span>
               </div>
               <div class="flex -space-x-2">
                <div class="flex">
                 <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-6" src="{{ asset('assets/metronic/media/avatars/300-3.png') }}"/>
                </div>
                <div class="flex">
                 <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-6" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}"/>
                </div>
               </div>
              </div>
              <div class="flex items-center gap-2.5">
               <span class="kt-badge kt-badge-sm kt-badge-success kt-badge-outline">
                Improvement
               </span>
               <span class="kt-badge kt-badge-sm kt-badge-destructive kt-badge-outline">
                Bug
               </span>
              </div>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5" id="notification_request_3">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-30.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Benjamin Harris
               </a>
               <span class="text-secondary-foreground">
                requested to upgrade plan
               </span>
               <a class="hover:text-primary text-primary" href="#">
               </a>
               <span class="text-secondary-foreground">
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               4 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Marketing
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Accept
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-24.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-1">
             <div class="text-sm font-medium mb-px">
              <a class="hover:text-primary text-mono font-semibold" href="#">
               Isaac Morgan
              </a>
              <span class="text-secondary-foreground">
               mentioned you in
              </span>
              <a class="hover:text-primary text-primary" href="#">
               Data Transmission
              </a>
              topic
             </div>
             <span class="flex items-center text-xs font-medium text-muted-foreground">
              6 days ago
              <span class="rounded-full size-1 bg-mono/30 mx-1.5">
              </span>
              Dev Team
             </span>
            </div>
           </div>
          </div>
         </div>
         <div class="border-b border-b-border">
         </div>
         <div class="grid grid-cols-2 p-5 gap-2.5" id="notifications_inbox_footer">
          <button class="kt-btn kt-btn-outline justify-center">
           Archive all
          </button>
          <button class="kt-btn kt-btn-outline justify-center">
           Mark all as read
          </button>
         </div>
        </div>
        <div class="grow flex flex-col hidden" id="notifications_tab_team">
         <div class="grow kt-scrollable-y-auto" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="150px">
          <div class="flex flex-col gap-5 pt-3 pb-4">
           <div class="flex grow gap-2 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-15.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3 grow" id="notification_request_10">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Nova Hawthorne
               </a>
               <span class="text-secondary-foreground">
                sent you an meeting invation
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               2 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Dev Team
              </span>
             </div>
             <div class="kt-card shadow-none p-2.5 rounded-lg bg-muted/70">
              <div class="flex items-center justify-between flex-wrap gap-2.5">
               <div class="flex items-center gap-2.5">
                <div class="border border-primary/10 rounded-lg">
                 <div class="flex items-center justify-center border-b border-b-primary/10 bg-primary/10 rounded-t-lg">
                  <span class="text-xs text-primary font-medium p-1.5">
                   Apr
                  </span>
                 </div>
                 <div class="flex items-center justify-center size-9">
                  <span class="font-semibold text-mono text-md tracking-tight">
                   12
                  </span>
                 </div>
                </div>
                <div class="flex flex-col gap-1.5">
                 <a class="hover:text-primary font-medium text-secondary-foreground text-xs" href="#">
                  Peparation For Release
                 </a>
                 <span class="font-medium text-secondary-foreground text-xs">
                  9:00 PM - 10:00 PM
                 </span>
                </div>
               </div>
               <div class="flex -space-x-2">
                <div class="flex">
                 <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-6" src="{{ asset('assets/metronic/media/avatars/300-4.png') }}"/>
                </div>
                <div class="flex">
                 <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-6" src="{{ asset('assets/metronic/media/avatars/300-1.png') }}"/>
                </div>
                <div class="flex">
                 <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-6" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}"/>
                </div>
                <div class="flex">
                 <span class="hover:z-5 relative inline-flex items-center justify-center shrink-0 rounded-full ring-1 font-semibold leading-none text-2xs size-6 text-white size-6 ring-background bg-green-500">
                  +3
                 </span>
                </div>
               </div>
              </div>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_10">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_10">
               Accept
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-6.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-1">
             <div class="text-sm font-medium mb-px">
              <a class="hover:text-primary text-mono font-semibold" href="#">
               Adrian Vale
              </a>
              <span class="text-secondary-foreground">
               change the due date of
              </span>
              <a class="hover:text-primary text-primary" href="#">
               Marketing
              </a>
              to 13 May
             </div>
             <span class="flex items-center text-xs font-medium text-muted-foreground">
              2 days ago
              <span class="rounded-full size-1 bg-mono/30 mx-1.5">
              </span>
              Marketing
             </span>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-12.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5 grow">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Skylar Frost
               </a>
               <span class="text-secondary-foreground">
                uploaded 2 attachments
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Web Design
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center justify-between flex-row gap-1.5 p-2.5 rounded-lg bg-muted/70">
              <div class="flex items-center gap-1.5">
               <img class="h-6" src="{{ asset('assets/metronic/media/file-types/word.svg') }}"/>
               <div class="flex flex-col gap-0.5">
                <a class="hover:text-primary font-medium text-secondary-foreground text-xs" href="#">
                 Landing-page.docx
                </a>
                <span class="font-medium text-muted-foreground text-xs">
                 1.9 MB
                </span>
               </div>
              </div>
              <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost">
               <svg fill="none" height="14" viewbox="0 0 14 14" width="14" xmlns="http://www.w3.org/2000/svg">
                <path clip-rule="evenodd" d="M6.63821 2.60467C4.81926 2.60467 3.32474 3.99623 3.16201 5.77252C3.1386 6.02803 2.92413 6.22253 2.66871 6.22227C1.74915 6.22149 0.976744 6.9868 0.976744 7.90442C0.976744 8.83344 1.72988 9.58657 2.65891 9.58657H3.09302C3.36274 9.58657 3.5814 9.80523 3.5814 10.0749C3.5814 10.3447 3.36274 10.5633 3.09302 10.5633H2.65891C1.19044 10.5633 0 9.37292 0 7.90442C0 6.58614 0.986948 5.48438 2.24496 5.27965C2.62863 3.20165 4.44941 1.62793 6.63821 1.62793C8.26781 1.62793 9.69282 2.50042 10.4729 3.80193C12.3411 3.72829 14 5.2564 14 7.18091C14 8.93508 12.665 10.3769 10.9552 10.5466C10.6868 10.5733 10.4476 10.3773 10.421 10.1089C10.3943 9.84052 10.5903 9.60135 10.8587 9.57465C12.0739 9.45406 13.0233 8.42802 13.0233 7.18091C13.0233 5.74002 11.6905 4.59666 10.2728 4.79968C10.0642 4.82957 9.85672 4.72382 9.76028 4.53181C9.18608 3.38796 8.00318 2.60467 6.63821 2.60467Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
                <path clip-rule="evenodd" d="M6.99909 8.01611L8.28162 9.29864C8.47235 9.48937 8.78158 9.48937 8.97231 9.29864C9.16303 9.10792 9.16303 8.79874 8.97231 8.60802L7.57465 7.2103C7.25675 6.89247 6.74143 6.89247 6.42353 7.2103L5.02585 8.60802C4.83513 8.79874 4.83513 9.10792 5.02585 9.29864C5.21657 9.48937 5.5258 9.48937 5.71649 9.29864L6.99909 8.01611Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
                <path clip-rule="evenodd" d="M7.00009 12.372C7.2698 12.372 7.48846 12.1533 7.48846 11.8836V7.97665C7.48846 7.70694 7.2698 7.48828 7.00009 7.48828C6.73038 7.48828 6.51172 7.70694 6.51172 7.97665V11.8836C6.51172 12.1533 6.73038 12.372 7.00009 12.372Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
               </svg>
              </button>
             </div>
             <div class="kt-card shadow-none flex items-center justify-between flex-row gap-1.5 p-2.5 rounded-lg bg-muted/70">
              <div class="flex items-center gap-1.5">
               <img class="h-6" src="{{ asset('assets/metronic/media/file-types/svg.svg') }}"/>
               <div class="flex flex-col gap-0.5">
                <a class="hover:text-primary font-medium text-secondary-foreground text-xs" href="#">
                 New-icon.svg
                </a>
                <span class="font-medium text-muted-foreground text-xs">
                 2.3 MB
                </span>
               </div>
              </div>
              <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost">
               <svg fill="none" height="14" viewbox="0 0 14 14" width="14" xmlns="http://www.w3.org/2000/svg">
                <path clip-rule="evenodd" d="M6.63821 2.60467C4.81926 2.60467 3.32474 3.99623 3.16201 5.77252C3.1386 6.02803 2.92413 6.22253 2.66871 6.22227C1.74915 6.22149 0.976744 6.9868 0.976744 7.90442C0.976744 8.83344 1.72988 9.58657 2.65891 9.58657H3.09302C3.36274 9.58657 3.5814 9.80523 3.5814 10.0749C3.5814 10.3447 3.36274 10.5633 3.09302 10.5633H2.65891C1.19044 10.5633 0 9.37292 0 7.90442C0 6.58614 0.986948 5.48438 2.24496 5.27965C2.62863 3.20165 4.44941 1.62793 6.63821 1.62793C8.26781 1.62793 9.69282 2.50042 10.4729 3.80193C12.3411 3.72829 14 5.2564 14 7.18091C14 8.93508 12.665 10.3769 10.9552 10.5466C10.6868 10.5733 10.4476 10.3773 10.421 10.1089C10.3943 9.84052 10.5903 9.60135 10.8587 9.57465C12.0739 9.45406 13.0233 8.42802 13.0233 7.18091C13.0233 5.74002 11.6905 4.59666 10.2728 4.79968C10.0642 4.82957 9.85672 4.72382 9.76028 4.53181C9.18608 3.38796 8.00318 2.60467 6.63821 2.60467Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
                <path clip-rule="evenodd" d="M6.99909 8.01611L8.28162 9.29864C8.47235 9.48937 8.78158 9.48937 8.97231 9.29864C9.16303 9.10792 9.16303 8.79874 8.97231 8.60802L7.57465 7.2103C7.25675 6.89247 6.74143 6.89247 6.42353 7.2103L5.02585 8.60802C4.83513 8.79874 4.83513 9.10792 5.02585 9.29864C5.21657 9.48937 5.5258 9.48937 5.71649 9.29864L6.99909 8.01611Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
                <path clip-rule="evenodd" d="M7.00009 12.372C7.2698 12.372 7.48846 12.1533 7.48846 11.8836V7.97665C7.48846 7.70694 7.2698 7.48828 7.00009 7.48828C6.73038 7.48828 6.51172 7.70694 6.51172 7.97665V11.8836C6.51172 12.1533 6.73038 12.372 7.00009 12.372Z" fill="#99A1B7" fill-rule="evenodd">
                </path>
               </svg>
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-21.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Selene Silverleaf
               </a>
               <span class="text-secondary-foreground">
                commented on
               </span>
               <a class="hover:text-primary text-primary" href="#">
                SiteSculpt
               </a>
               <span class="text-secondary-foreground">
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               4 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Manager
              </span>
             </div>
             <div class="kt-card shadow-none flex flex-col gap-2.5 p-3.5 rounded-lg bg-muted/70">
              <div class="text-sm font-semibold text-secondary-foreground mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                @Cody
               </a>
               <span class="text-secondary-foreground font-medium">
                This
		design is simply stunning! From layout to color, it's a work of art!
               </span>
              </div>
              <div class="kt-input">
               <input placeholder="Reply" type="text" value=""/>
               <button class="kt-btn kt-btn-ghost kt-btn-icon size-6 -me-1.5">
                <i class="ki-filled ki-picture">
                </i>
               </button>
              </div>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5" id="notification_request_3">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-13.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Thalia Fox
               </a>
               <span class="text-secondary-foreground">
                has invited you
		to join
               </span>
               <a class="hover:text-primary text-primary" href="#">
                Design Research
               </a>
               <span class="text-secondary-foreground">
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               4 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Dev
		Team
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Accept
              </button>
             </div>
            </div>
           </div>
          </div>
         </div>
         <div class="border-b border-b-border">
         </div>
         <div class="grid grid-cols-2 p-5 gap-2.5" id="notifications_team_footer">
          <button class="kt-btn kt-btn-outline justify-center">
           Archive all
          </button>
          <button class="kt-btn kt-btn-outline justify-center">
           Mark all as read
          </button>
         </div>
        </div>
        <div class="grow flex flex-col hidden" id="notifications_tab_following">
         <div class="grow kt-scrollable-y-auto" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="150px">
          <div class="flex flex-col gap-5 pt-3 pb-4">
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-1.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-2.5 grow">
             <div class="flex flex-col gap-1 mb-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Jane Perez
               </a>
               <span class="text-secondary-foreground">
                added 2 new works to
               </span>
               <a class="hover:text-primary text-primary font-semibold" href="#">
                Inspirations 2024
               </a>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               23 hours ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Craftwork Design
              </span>
             </div>
             <div class="flex items-center gap-2.5">
              <div class="kt-card shadow-none flex flex-col gap-3.5 bg-muted/70 w-40">
               <div class="bg-cover bg-no-repeat kt-card-rounded-t shrink-0 h-24" style="background-image: url('{{ asset('assets/metronic/media/images/600x600/6.jpg') }}')">
               </div>
               <div class="px-2.5 pb-2">
                <a class="font-medium block text-secondary-foreground hover:text-primary text-xs leading-4 mb-0.5" href="#">
                 Geometric Patterns
                </a>
                <div class="text-xs font-medium text-muted-foreground">
                 Token ID:
                 <span class="text-xs font-medium text-secondary-foreground">
                  81023
                 </span>
                </div>
               </div>
              </div>
              <div class="kt-card shadow-none flex flex-col gap-3.5 bg-muted/70 w-40">
               <div class="bg-cover bg-no-repeat kt-card-rounded-t shrink-0 h-24" style="background-image: url('{{ asset('assets/metronic/media/images/600x600/1.jpg') }}')">
               </div>
               <div class="px-2.5 pb-2">
                <a class="font-medium block text-secondary-foreground hover:text-primary text-xs leading-4 mb-0.5" href="#">
                 Artistic Expressions
                </a>
                <div class="text-xs font-medium text-muted-foreground">
                 Token ID:
                 <span class="text-xs font-medium text-secondary-foreground">
                  67890
                 </span>
                </div>
               </div>
              </div>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5" id="notification_request_17">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-19.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-2.5 grow">
             <div class="flex flex-col gap-1 mb-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Natalie Wood
               </a>
               <span class="text-secondary-foreground">
                wants to edit marketing project
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               1 day ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Designer
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center flex-row gap-1.5 p-2.5 rounded-lg bg-muted/70">
              <div class="flex items-center justify-center w-[26px] h-[30px] shrink-0 bg-white rounded-sm border border-border">
               <img class="h-5" src="{{ asset('assets/metronic/media/brand-logos/jira.svg') }}"/>
              </div>
              <a class="hover:text-primary font-medium text-secondary-foreground text-xs me-1" href="#">
               User-feedback.jira
              </a>
              <span class="font-medium text-muted-foreground text-xs">
               Edited 1 hour ago
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_17">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_17">
               Accept
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-17.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-2.5 grow">
             <div class="flex flex-col gap-1 mb-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Aaron Foster
               </a>
               <span class="text-secondary-foreground">
                requested to view
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 day ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Larsen Ltd
              </span>
             </div>
             <div class="kt-card shadow-none flex items-center flex-row gap-1.5 px-2.5 py-1.5 rounded-lg bg-muted/70">
              <i class="ki-filled ki-user-tick text-green-500 text-base">
              </i>
              <span class="font-medium text-green-500 text-sm">
               You allowed Aaron to view
              </span>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-34.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-1">
             <div class="text-sm font-medium mb-px">
              <a class="hover:text-primary text-mono font-semibold" href="#">
               Chloe Morgan
              </a>
              <span class="text-secondary-foreground">
               posted a new
		article
              </span>
              <a class="hover:text-primary text-primary" href="#">
               User Experience
              </a>
             </div>
             <span class="flex items-center text-xs font-medium text-muted-foreground">
              1 day ago
              <span class="rounded-full size-1 bg-mono/30 mx-1.5">
              </span>
              Nexus
             </span>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-9.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-2.5 grow">
             <div class="flex flex-col gap-1 mb-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Gabriel Bennett
               </a>
               <span class="text-secondary-foreground">
                started connect you
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               3 day ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Development
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-sm kt-btn-outline">
               <i class="ki-filled ki-check-circle">
               </i>
               Connected
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm">
               Go to profile
              </button>
             </div>
            </div>
           </div>
           <div class="border-b border-b-border">
           </div>
           <div class="flex grow gap-2.5 px-5" id="notification_request_3">
            <div class="kt-avatar size-8">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-13.png') }}"/>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
            <div class="flex flex-col gap-3.5">
             <div class="flex flex-col gap-1">
              <div class="text-sm font-medium mb-px">
               <a class="hover:text-primary text-mono font-semibold" href="#">
                Thalia Fox
               </a>
               <span class="text-secondary-foreground">
                has invited you
		to join
               </span>
               <a class="hover:text-primary text-primary" href="#">
                Design Research
               </a>
               <span class="text-secondary-foreground">
               </span>
              </div>
              <span class="flex items-center text-xs font-medium text-muted-foreground">
               4 days ago
               <span class="rounded-full size-1 bg-mono/30 mx-1.5">
               </span>
               Dev
		Team
              </span>
             </div>
             <div class="flex flex-wrap gap-2.5">
              <button class="kt-btn kt-btn-outline kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Decline
              </button>
              <button class="kt-btn kt-btn-mono kt-btn-sm" data-kt-dismiss="#notification_request_3">
               Accept
              </button>
             </div>
            </div>
           </div>
          </div>
         </div>
         <div class="border-b border-b-border">
         </div>
         <div class="grid grid-cols-2 p-5 gap-2.5" id="notifications_following_footer">
          <button class="kt-btn kt-btn-outline justify-center">
           Archive all
          </button>
          <button class="kt-btn kt-btn-outline justify-center">
           Mark all as read
          </button>
         </div>
        </div>
       </div>
       <!--End of Notifications Drawer-->
       <!-- End of Notifications -->
       <!-- Chat -->
       <button class="kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary" data-kt-drawer-toggle="#chat_drawer">
        <i class="ki-filled ki-messages text-lg">
        </i>
       </button>
       <!--Chat Drawer-->
       <div class="hidden kt-drawer kt-drawer-end card flex-col max-w-[90%] w-[450px] top-5 bottom-5 end-5 rounded-xl border border-border" data-kt-drawer="true" data-kt-drawer-container="body" id="chat_drawer">
        <div>
         <div class="flex items-center justify-between gap-2.5 text-sm text-mono font-semibold px-5 py-3.5">
          Chat
          <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-dim shrink-0" data-kt-drawer-dismiss="true">
           <i class="ki-filled ki-cross">
           </i>
          </button>
         </div>
         <div class="border-b border-b-border">
         </div>
         <div class="border-b border-border py-2.5">
          <div class="flex items-center justify-between flex-wrap gap-2 px-5">
           <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-11">
             <img alt="" class="size-7" src="{{ asset('assets/metronic/media/brand-logos/gitlab.svg') }}"/>
            </div>
            <div class="flex flex-col">
             <a class="text-sm font-semibold text-mono hover:text-primary" href="#">
              HR Team
             </a>
             <span class="text-xs font-medium italic text-muted-foreground">
              Jessy is typing..
             </span>
            </div>
           </div>
           <div class="flex items-center gap-2.5">
            <div class="flex -space-x-2">
             <div class="flex">
              <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-[30px]" src="{{ asset('assets/metronic/media/avatars/300-4.png') }}"/>
             </div>
             <div class="flex">
              <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-[30px]" src="{{ asset('assets/metronic/media/avatars/300-1.png') }}"/>
             </div>
             <div class="flex">
              <img class="hover:z-5 relative shrink-0 rounded-full ring-1 ring-background size-[30px]" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}"/>
             </div>
             <div class="flex">
              <span class="hover:z-5 relative inline-flex items-center justify-center shrink-0 rounded-full ring-1 font-semibold leading-none text-2xs size-[30px] text-white size-6 ring-background bg-green-500">
               +10
              </span>
             </div>
            </div>
            <div class="kt-menu" data-kt-menu="true">
             <div class="kt-menu-item" data-kt-menu-item-offset="0, 10px" data-kt-menu-item-placement="bottom-end" data-kt-menu-item-placement-rtl="bottom-start" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click|lg:hover">
              <button class="kt-menu-toggle kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost">
               <i class="ki-filled ki-dots-vertical text-lg">
               </i>
              </button>
              <div class="kt-menu-dropdown kt-menu-default w-full max-w-[175px]" data-kt-menu-dismiss="true">
               <div class="kt-menu-item">
                <a class="kt-menu-link" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-users">
                  </i>
                 </span>
                 <span class="kt-menu-title">
                  Invite Users
                 </span>
                </a>
               </div>
               <div class="kt-menu-item" data-kt-menu-item-offset="-15px, 0" data-kt-menu-item-placement="right-start" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click|lg:hover">
                <div class="kt-menu-link">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-people">
                  </i>
                 </span>
                 <span class="kt-menu-title">
                  Team
                 </span>
                 <span class="kt-menu-arrow">
                  <i class="ki-filled ki-right text-xs rtl:transform rtl:rotate-180">
                  </i>
                 </span>
                </div>
                <div class="kt-menu-dropdown kt-menu-default w-full max-w-[175px]">
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-shield-search">
                    </i>
                   </span>
                   <span class="kt-menu-title">
                    Find Members
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-calendar">
                    </i>
                   </span>
                   <span class="kt-menu-title">
                    Meetings
                   </span>
                  </a>
                 </div>
                 <div class="kt-menu-item">
                  <a class="kt-menu-link" href="#">
                   <span class="kt-menu-icon">
                    <i class="ki-filled ki-filter-edit">
                    </i>
                   </span>
                   <span class="kt-menu-title">
                    Group Settings
                   </span>
                  </a>
                 </div>
                </div>
               </div>
               <div class="kt-menu-item">
                <a class="kt-menu-link" href="#">
                 <span class="kt-menu-icon">
                  <i class="ki-filled ki-setting-3">
                  </i>
                 </span>
                 <span class="kt-menu-title">
                  Settings
                 </span>
                </a>
               </div>
              </div>
             </div>
            </div>
           </div>
          </div>
         </div>
        </div>
        <div class="kt-scrollable-y-auto grow" data-kt-scrollable="true" data-kt-scrollable-dependencies="#header" data-kt-scrollable-max-height="auto" data-kt-scrollable-offset="230px">
         <div class="flex flex-col gap-5 py-5">
          <div class="flex items-end gap-3.5 px-5">
           <img alt="" class="rounded-full size-9" src="{{ asset('assets/metronic/media/avatars/300-5.png') }}"/>
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex flex-col bg-accent/60 gap-2.5 p-3 rounded-bs-none text-2sm">
             Next week we are closing the project. Do You have questions?
            </div>
            <span class="text-xs font-medium text-muted-foreground">
             14:04
            </span>
           </div>
          </div>
          <div class="flex items-end justify-end gap-3.5 px-5">
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex bg-primary flex-col gap-2.5 p-3 rounded-be-none">
             <p class="text-2sm font-medium text-primary-foreground">
              This is excellent news!
             </p>
            </div>
            <div class="flex items-center justify-end gap-2 relative">
             <span class="text-xs font-medium text-secondary-foreground">
              14:08
             </span>
             <i class="ki-filled ki-double-check text-lg absolute text-green-500">
             </i>
            </div>
           </div>
           <div class="relative shrink-0">
            <div class="kt-avatar size-9">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
           </div>
          </div>
          <div class="flex items-end gap-3.5 px-5">
           <img alt="" class="rounded-full size-9" src="{{ asset('assets/metronic/media/avatars/300-4.png') }}"/>
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex flex-col bg-accent/60 gap-2.5 p-3 rounded-bs-none text-2sm">
             I have checked the features, can not wait to demo them!
            </div>
            <span class="text-xs font-medium text-muted-foreground">
             14:26
            </span>
           </div>
          </div>
          <div class="flex items-end gap-3.5 px-5">
           <img alt="" class="rounded-full size-9" src="{{ asset('assets/metronic/media/avatars/300-1.png') }}"/>
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex flex-col bg-accent/60 gap-2.5 p-3 rounded-bs-none text-2sm">
             I have looked over the rollout plan, and everything seems spot on.
            </div>
            <span class="text-xs font-medium text-muted-foreground">
             15:09
            </span>
           </div>
          </div>
          <div class="flex items-end justify-end gap-3.5 px-5">
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex bg-primary flex-col gap-2.5 p-3 rounded-be-none">
             <p class="text-2sm font-medium text-primary-foreground">
              Haven't seen the build yet, I'll look now.
             </p>
            </div>
            <div class="flex items-center justify-end gap-2 relative">
             <span class="text-xs font-medium text-secondary-foreground">
              15:52
             </span>
             <i class="ki-filled ki-double-check text-lg absolute text-muted-foreground">
             </i>
            </div>
           </div>
           <div class="relative shrink-0">
            <div class="kt-avatar size-9">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
           </div>
          </div>
          <div class="flex items-end justify-end gap-3.5 px-5">
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex bg-primary flex-col gap-2.5 p-3 rounded-be-none">
             <p class="text-2sm font-medium text-primary-foreground">
              Checking the build now
             </p>
            </div>
            <div class="flex items-center justify-end gap-2 relative">
             <span class="text-xs font-medium text-secondary-foreground">
              15:52
             </span>
             <i class="ki-filled ki-double-check text-lg absolute text-muted-foreground">
             </i>
            </div>
           </div>
           <div class="relative shrink-0">
            <div class="kt-avatar size-9">
             <div class="kt-avatar-image">
              <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}">
              </img>
             </div>
             <div class="kt-avatar-indicator -end-2 -bottom-2">
              <div class="kt-avatar-status kt-avatar-status-online size-2.5">
              </div>
             </div>
            </div>
           </div>
          </div>
          <div class="flex items-end gap-3.5 px-5">
           <img alt="" class="rounded-full size-9" src="{{ asset('assets/metronic/media/avatars/300-4.png') }}"/>
           <div class="flex flex-col gap-1.5">
            <div class="kt-card shadow-none flex flex-col bg-accent/60 gap-2.5 p-3 rounded-bs-none text-2sm">
             Tomorrow, I will send the link for the meeting
            </div>
            <span class="text-xs font-medium text-muted-foreground">
             17:40
            </span>
           </div>
          </div>
         </div>
        </div>
        <!--Chat Footer-->
        <div class="mb-2.5">
         <div class="flex grow gap-2 px-5 py-3.5 bg-accent/60 mb-2.5 border-y border-border" id="join_request">
          <div class="kt-avatar size-9">
           <div class="kt-avatar-image">
            <img alt="avatar" src="{{ asset('assets/metronic/media/avatars/300-14.png') }}">
            </img>
           </div>
           <div class="kt-avatar-indicator -end-2 -bottom-2">
            <div class="kt-avatar-status kt-avatar-status-online size-2.5">
            </div>
           </div>
          </div>
          <div class="flex items-center justify-between gap-3 grow">
           <div class="flex flex-col">
            <div class="text-sm mb-px">
             <a class="hover:text-primary font-semibold text-mono" href="#">
              Jane Perez
             </a>
             <span class="text-secondary-foreground">
              wants to join chat
             </span>
            </div>
            <span class="flex items-center text-xs font-medium text-muted-foreground">
             1 day ago
             <span class="rounded-full size-1 bg-mono/30 mx-1.5">
             </span>
             Design Team
            </span>
           </div>
           <div class="flex gap-2.5">
            <button class="kt-btn kt-btn-sm kt-btn-outline kt-btn-sm" data-kt-dismiss="#join_request">
             Decline
            </button>
            <button class="kt-btn kt-btn-sm kt-btn-mono kt-btn-sm">
             Accept
            </button>
           </div>
          </div>
         </div>
         <div class="relative grow mx-5">
          <img alt="" class="rounded-full size-[30px] absolute start-0 top-2/4 -translate-y-2/4 ms-2.5" src="{{ asset('assets/metronic/media/avatars/300-2.png') }}">
           <input class="kt-input h-auto py-4 ps-12 bg-transparent" placeholder="Write a message..." type="text" value=""/>
           <div class="flex items-center gap-2.5 absolute end-3 top-1/2 -translate-y-1/2">
            <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost">
             <i class="ki-filled ki-exit-up">
             </i>
            </button>
            <a class="kt-btn kt-btn-mono kt-btn-sm" href="#">
             Send
            </a>
           </div>
          </img>
         </div>
        </div>
        <!--End of Chat Footer-->
       </div>
       <!--End of Chat Drawer-->
       <!-- End of Chat -->
       <!-- Apps -->
       <div data-kt-dropdown="true" data-kt-dropdown-offset="10px, 10px" data-kt-dropdown-offset-rtl="-10px, 10px" data-kt-dropdown-placement="bottom-end" data-kt-dropdown-placement-rtl="bottom-start">
        <button class="kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10 hover:[&_i]:text-primary kt-dropdown-open:bg-primary/10 kt-dropdown-open:[&_i]:text-primary" data-kt-dropdown-toggle="true">
         <i class="ki-filled ki-element-11 text-lg">
         </i>
        </button>
        <div class="kt-dropdown-menu p-0 w-screen max-w-[320px]" data-kt-dropdown-menu="true">
         <div class="flex items-center justify-between gap-2.5 text-xs text-secondary-foreground font-medium px-5 py-3 border-b border-b-border">
          <span>
           Apps
          </span>
          <span>
           Enabled
          </span>
         </div>
         <div class="flex flex-col kt-scrollable-y-auto max-h-[400px] divide-y divide-border">
          <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5">
           <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-10">
             <img alt="" class="size-6" src="{{ asset('assets/metronic/media/brand-logos/jira.svg') }}">
             </img>
            </div>
            <div class="flex flex-col">
             <a class="text-sm font-semibold text-mono hover:text-primary" href="#">
              Jira
             </a>
             <span class="text-xs font-medium text-secondary-foreground">
              Project management
             </span>
            </div>
           </div>
           <div class="flex items-center gap-2 lg:gap-5">
            <input class="kt-switch" type="checkbox" value="1"/>
           </div>
          </div>
          <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5">
           <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-10">
             <img alt="" class="size-6" src="{{ asset('assets/metronic/media/brand-logos/inferno.svg') }}">
             </img>
            </div>
            <div class="flex flex-col">
             <a class="text-sm font-semibold text-mono hover:text-primary" href="#">
              Inferno
             </a>
             <span class="text-xs font-medium text-secondary-foreground">
              Ensures healthcare app
             </span>
            </div>
           </div>
           <div class="flex items-center gap-2 lg:gap-5">
            <input checked="" class="kt-switch" type="checkbox" value="1"/>
           </div>
          </div>
          <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5">
           <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-10">
             <img alt="" class="size-6" src="{{ asset('assets/metronic/media/brand-logos/evernote.svg') }}"/>
            </div>
            <div class="flex flex-col">
             <a class="text-sm font-semibold text-mono hover:text-primary" href="#">
              Evernote
             </a>
             <span class="text-xs font-medium text-secondary-foreground">
              Notes management app
             </span>
            </div>
           </div>
           <div class="flex items-center gap-2 lg:gap-5">
            <input checked="" class="kt-switch" type="checkbox" value="1"/>
           </div>
          </div>
          <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5">
           <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-10">
             <img alt="" class="size-6" src="{{ asset('assets/metronic/media/brand-logos/gitlab.svg') }}"/>
            </div>
            <div class="flex flex-col">
             <a class="text-sm font-semibold text-mono hover:text-primary" href="#">
              Gitlab
             </a>
             <span class="text-xs font-medium text-secondary-foreground">
              DevOps platform
             </span>
            </div>
           </div>
           <div class="flex items-center gap-2 lg:gap-5">
            <input class="kt-switch" type="checkbox" value="1"/>
           </div>
          </div>
          <div class="flex items-center justify-between flex-wrap gap-2 px-5 py-3.5">
           <div class="flex items-center flex-wrap gap-2">
            <div class="flex items-center justify-center shrink-0 rounded-full bg-accent/60 border border-border size-10">
             <img alt="" class="size-6" src="{{ asset('assets/metronic/media/brand-logos/google-webdev.svg') }}"/>
            </div>
            <div class="flex flex-col">
             <a class="text-sm font-semibold text-mono hover:text-primary" href="#">
              Google webdev
             </a>
             <span class="text-xs font-medium text-secondary-foreground">
              Building web expierences
             </span>
            </div>
           </div>
           <div class="flex items-center gap-2 lg:gap-5">
            <input checked="" class="kt-switch" type="checkbox" value="1"/>
           </div>
          </div>
         </div>
         <div class="grid p-5 border-t border-t-border">
          <a class="kt-btn kt-btn-outline justify-center" href="#">
           Go to Apps
          </a>
         </div>
        </div>
       </div>
       <!-- End of Apps -->
       <!-- User -->
       <div class="shrink-0" data-kt-dropdown="true" data-kt-dropdown-offset="10px, 10px" data-kt-dropdown-offset-rtl="-20px, 10px" data-kt-dropdown-placement="bottom-end" data-kt-dropdown-placement-rtl="bottom-start" data-kt-dropdown-trigger="click">
        <div class="cursor-pointer shrink-0" data-kt-dropdown-toggle="true">
         <img alt="" class="size-9 rounded-full border-2 border-green-500 shrink-0" src="{{ $userAvatarUrl }}"/>
        </div>
        <div class="kt-dropdown-menu w-[250px]" data-kt-dropdown-menu="true">
         <div class="flex items-center justify-between px-2.5 py-1.5 gap-1.5">
          <div class="flex items-center gap-2">
           <img alt="" class="size-9 shrink-0 rounded-full border-2 border-green-500" src="{{ $userAvatarUrl }}"/>
           <div class="flex flex-col gap-1.5">
            <span class="text-sm text-foreground font-semibold leading-none">
             {{ $userName }}
            </span>
            <a class="text-xs text-secondary-foreground hover:text-primary font-medium leading-none" href="#">
             {{ $userEmail }}
            </a>
           </div>
          </div>
          <span class="kt-badge kt-badge-sm kt-badge-primary kt-badge-outline">
           Pro
          </span>
         </div>
         <ul class="kt-dropdown-menu-sub">
          <li>
           <div class="kt-dropdown-menu-separator">
           </div>
          </li>
          <li>
           <a class="kt-dropdown-menu-link" href="{{ $profileUrl ?? '#' }}">
            <i class="ki-filled ki-badge">
            </i>
            Public Profile
           </a>
          </li>
          <li>
           <a class="kt-dropdown-menu-link" href="{{ $profileUrl ?? '#' }}">
            <i class="ki-filled ki-profile-circle">
            </i>
            My Profile
           </a>
          </li>
          <li data-kt-dropdown="true" data-kt-dropdown-placement="right-start" data-kt-dropdown-trigger="hover">
           <button class="kt-dropdown-menu-toggle" data-kt-dropdown-toggle="true">
            <i class="ki-filled ki-setting-2">
            </i>
            My Account
            <span class="kt-dropdown-menu-indicator">
             <i class="ki-filled ki-right text-xs">
             </i>
            </span>
           </button>
           <div class="kt-dropdown-menu w-[220px]" data-kt-dropdown-menu="true">
            <ul class="kt-dropdown-menu-sub">
             <li>
              <a class="kt-dropdown-menu-link" href="{{ $homeUrl }}">
               <i class="ki-filled ki-coffee">
               </i>
               Get Started
              </a>
             </li>
             <li>
              <a class="kt-dropdown-menu-link" href="{{ $profileUrl ?? '#' }}">
               <i class="ki-filled ki-some-files">
               </i>
               My Profile
              </a>
             </li>
             <li>
              <a class="kt-dropdown-menu-link" href="{{ $billingSettingsUrl ?? '#' }}">
               <span class="flex items-center gap-2">
                <i class="ki-filled ki-icon">
                </i>
                Billing
               </span>
               <span class="ms-auto inline-flex items-center" data-kt-tooltip="true" data-kt-tooltip-placement="top">
                <i class="ki-filled ki-information-2 text-base text-muted-foreground">
                </i>
                <span class="kt-tooltip" data-kt-tooltip-content="true">
                 Payment and subscription info
                </span>
               </span>
              </a>
             </li>
             <li>
              <a class="kt-dropdown-menu-link" href="#">
               <i class="ki-filled ki-medal-star">
               </i>
               Security
              </a>
             </li>
             <li>
              <a class="kt-dropdown-menu-link" href="{{ $rolesUrl ?? '#' }}">
               <i class="ki-filled ki-setting">
               </i>
               Members & Roles
              </a>
             </li>
             <li>
              <a class="kt-dropdown-menu-link" href="#">
               <i class="ki-filled ki-switch">
               </i>
               Integrations
              </a>
             </li>
             <li>
              <div class="kt-dropdown-menu-separator">
              </div>
             </li>
             <li>
              <a class="kt-dropdown-menu-link" href="#">
               <span class="flex items-center gap-2">
                <i class="ki-filled ki-shield-tick">
                </i>
                Notifications
               </span>
               <input checked="" class="ms-auto kt-switch" name="check" type="checkbox" value="1"/>
              </a>
             </li>
            </ul>
           </div>
          </li>
          <li>
           <a class="kt-dropdown-menu-link" href="#">
            <i class="ki-filled ki-message-programming">
            </i>
            Dev Forum
           </a>
          </li>
          <li data-kt-dropdown="true" data-kt-dropdown-placement="right-start" data-kt-dropdown-trigger="hover">
           <button class="kt-dropdown-menu-toggle py-1" data-kt-dropdown-toggle="true">
            <span class="flex items-center gap-2">
             <i class="ki-filled ki-icon">
             </i>
             Language
            </span>
            <span class="ms-auto kt-badge kt-badge-stroke shrink-0">
             English
             <img alt="" class="inline-block size-3.5 rounded-full" src="{{ asset('assets/metronic/media/flags/united-states.svg') }}"/>
            </span>
           </button>
           <div class="kt-dropdown-menu w-[180px]" data-kt-dropdown-menu="true">
            <ul class="kt-dropdown-menu-sub">
             <li class="active">
              <a class="kt-dropdown-menu-link" href="?dir=ltr">
               <span class="flex items-center gap-2">
                <img alt="" class="inline-block size-4 rounded-full" src="{{ asset('assets/metronic/media/flags/united-states.svg') }}"/>
                <span class="kt-menu-title">
                 English
                </span>
               </span>
               <i class="ki-solid ki-check-circle ms-auto text-green-500 text-base">
               </i>
              </a>
             </li>
             <li class="">
              <a class="kt-dropdown-menu-link" href="?dir=rtl">
               <span class="flex items-center gap-2">
                <img alt="" class="inline-block size-4 rounded-full" src="{{ asset('assets/metronic/media/flags/saudi-arabia.svg') }}"/>
                <span class="kt-menu-title">
                 Arabic(Saudi)
                </span>
               </span>
              </a>
             </li>
             <li class="">
              <a class="kt-dropdown-menu-link" href="?dir=ltr">
               <span class="flex items-center gap-2">
                <img alt="" class="inline-block size-4 rounded-full" src="{{ asset('assets/metronic/media/flags/spain.svg') }}"/>
                <span class="kt-menu-title">
                 Spanish
                </span>
               </span>
              </a>
             </li>
             <li class="">
              <a class="kt-dropdown-menu-link" href="?dir=ltr">
               <span class="flex items-center gap-2">
                <img alt="" class="inline-block size-4 rounded-full" src="{{ asset('assets/metronic/media/flags/germany.svg') }}"/>
                <span class="kt-menu-title">
                 German
                </span>
               </span>
              </a>
             </li>
             <li class="">
              <a class="kt-dropdown-menu-link" href="?dir=ltr">
               <span class="flex items-center gap-2">
                <img alt="" class="inline-block size-4 rounded-full" src="{{ asset('assets/metronic/media/flags/japan.svg') }}"/>
                <span class="kt-menu-title">
                 Japanese
                </span>
               </span>
              </a>
             </li>
            </ul>
           </div>
          </li>
          <li>
           <div class="kt-dropdown-menu-separator">
           </div>
          </li>
         </ul>
         <div class="px-2.5 pt-1.5 mb-2.5 flex flex-col gap-3.5">
          <div class="flex items-center gap-2 justify-between">
           <span class="flex items-center gap-2">
            <i class="ki-filled ki-moon text-base text-muted-foreground">
            </i>
            <span class="font-medium text-2sm">
             Dark Mode
            </span>
           </span>
           <input class="kt-switch" data-kt-theme-switch-state="dark" data-kt-theme-switch-toggle="true" name="check" type="checkbox" value="1"/>
          </div>
          <form action="{{ route('logout') }}" method="post">
           @csrf
           <button class="kt-btn kt-btn-outline justify-center w-full" type="submit">
            Log out
           </button>
          </form>
         </div>
        </div>
       </div>
       <!-- End of User -->
      </div>
      <!-- End of Topbar -->
            </div>
        </header>

        <main class="grow pt-5" id="content" role="content">
            <div class="kt-container-fixed">
                @yield('content')
            </div>
        </main>
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
            let type = "{{ Session::get('status', 'success') }}";
            switch (type) {
                case 'info':
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
                    toastr.info("{{ Session::get('message') }}", "Information");
                    break;
                case 'warning':
                    toastr.options = {
                        "debug": false,
                        "closeButton": true,
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
                    };
                    toastr.warning("{{ Session::get('message') }}", "Warning!!!");
                    break;
                case 'success':
                    toastr.options = {
                        "debug": false,
                        "closeButton": true,
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
                    };
                    toastr.success("{{ Session::get('message') }}", "Success");
                    break;
                case 'error':
                    toastr.options = {
                        "debug": false,
                        "closeButton": true,
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
                    };
                    toastr.error("{{ Session::get('message') }}", "Error");
                    break;
            }
        }
    </script>
@endif

@stack('modals')
@stack('custom-scripts')
@stack('scripts')
</body>
</html>
