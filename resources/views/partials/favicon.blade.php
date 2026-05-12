@php
    $faviconUrl = null;

    try {
        $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();

        if ($tenant) {
            $favicon = app(\App\Modules\CmsCore\Services\CmsThemeService::class)->favicon();
            $faviconUrl = $favicon?->url();
        }
    } catch (\Throwable) {
        $faviconUrl = null;
    }

    $faviconUrl ??= asset($default ?? 'assets/metronic/media/app/favicon.ico');
@endphp
<link rel="icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
