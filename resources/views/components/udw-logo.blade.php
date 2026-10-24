@props([
    'size' => 32,
    'badge' => true,
    'badgeColor' => '#2455b5',
    'badgeRadius' => 10,
    'cradleColor' => '#ffffff',
    'coreColor' => '#9ec0ff',
])

@php
    $sizeNum = is_numeric($size) ? (int) $size : 32;
    $strokeWidth = $sizeNum <= 20 ? 5.0 : 4.5;
@endphp

<svg
    {{ $attributes->merge([
        'width' => (string) $sizeNum,
        'height' => (string) $sizeNum,
        'viewBox' => '0 0 48 48',
        'fill' => 'none',
        'xmlns' => 'http://www.w3.org/2000/svg',
        'aria-hidden' => 'true',
    ]) }}
>
    @if ($badge)
        <rect width="48" height="48" rx="{{ $badgeRadius }}" fill="{{ $badgeColor }}" />
    @endif
    <path
        d="M12 14V26C12 32.6274 17.3726 38 24 38C30.6274 38 36 32.6274 36 26V14"
        stroke="{{ $cradleColor }}"
        stroke-width="{{ $strokeWidth }}"
        stroke-linecap="round"
    />
    <rect x="20" y="19" width="8" height="8" rx="2" fill="{{ $coreColor }}" />
</svg>

