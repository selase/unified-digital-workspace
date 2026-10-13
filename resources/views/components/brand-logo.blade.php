@props(['variant' => 'inline'])

@php
    /**
     * The organisation's own logo when one is uploaded, then the logo of its
     * public website if it has one, otherwise a wordmark built from its name
     * (or the platform name outside an organisation).
     * Error pages can render before an organisation is resolved, so every
     * lookup here must tolerate a missing or failing tenant context.
     */
    try {
        $brandTenant = app(\App\Services\Tenancy\TenantContext::class)->getTenant();
        $brandLogoUrl = $brandTenant?->logo_url;

        if ($brandTenant && blank($brandLogoUrl)) {
            $brandLogoUrl = app(\App\Modules\CmsCore\Services\CmsThemeService::class)->logo()?->url();
        }
    } catch (\Throwable) {
        $brandTenant ??= null;
        $brandLogoUrl = null;
    }

    $brandName = (string) ($brandTenant?->name ?: config('app.name'));
    $brandInitial = mb_strtoupper(mb_substr($brandName, 0, 1));
    $badgeClasses = 'inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground';
@endphp

@if($variant === 'sidebar')
    {{-- The sidebar's own CSS shows .default-logo or .small-logo depending on whether it is collapsed,
         so those two wrappers must not carry display utilities of their own. --}}
    @if($brandLogoUrl)
        <img class="default-logo max-h-9 max-w-[170px] object-contain" src="{{ $brandLogoUrl }}" alt="{{ $brandName }}"/>
        <img class="small-logo size-8 max-w-none object-contain" src="{{ $brandLogoUrl }}" alt="{{ $brandName }}"/>
    @else
        <span class="default-logo">
            <span class="flex items-center gap-2.5">
                <span class="{{ $badgeClasses }}" aria-hidden="true">{{ $brandInitial }}</span>
                <span class="max-w-[150px] truncate text-sm font-semibold text-foreground">{{ $brandName }}</span>
            </span>
        </span>
        <span class="small-logo" title="{{ $brandName }}">
            <span class="{{ $badgeClasses }}">{{ $brandInitial }}</span>
        </span>
    @endif
@elseif($brandLogoUrl)
    <img {{ $attributes->merge(['class' => 'max-h-10 max-w-[200px] object-contain']) }} src="{{ $brandLogoUrl }}" alt="{{ $brandName }}"/>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
        <span class="{{ $badgeClasses }}" aria-hidden="true">{{ $brandInitial }}</span>
        <span class="text-sm font-semibold text-foreground">{{ $brandName }}</span>
    </span>
@endif
