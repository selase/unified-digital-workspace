@php
    use App\Modules\CmsCore\Services\SiteIconService;

    $siteIcon = null;
    $themeColor = '#1d4ed8';
    $cacheBuster = null;

    try {
        $tenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();

        if ($tenant) {
            $themeService = app(\App\Modules\CmsCore\Services\CmsThemeService::class);
            $siteIcon = $themeService->siteIcon();
            $themeColor = $themeService->themeColor();
            $cacheBuster = app(SiteIconService::class)->cacheBuster();
        }
    } catch (\Throwable) {
        $siteIcon = null;
    }

    $bust = $cacheBuster ? '?v='.$cacheBuster : '';
@endphp
@if($siteIcon)
    <link rel="icon" type="image/png" sizes="32x32" href="{{ route('site-icon.serve', ['size' => 32]) }}{{ $bust }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ route('site-icon.serve', ['size' => 16]) }}{{ $bust }}">
    <link rel="icon" type="image/x-icon" href="{{ route('site-icon.favicon') }}{{ $bust }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ route('site-icon.apple') }}{{ $bust }}">
    <link rel="manifest" href="{{ route('site-icon.manifest') }}{{ $bust }}">
@else
    <link rel="icon" href="{{ asset($default ?? 'assets/metronic/media/app/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset($default ?? 'assets/metronic/media/app/favicon.ico') }}">
@endif
<meta name="theme-color" content="{{ $themeColor }}">
