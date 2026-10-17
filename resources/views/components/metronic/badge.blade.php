@props([
    'variant' => 'secondary', // primary, success, danger, warning, info, secondary
    'outline' => false,
    'size' => 'md', // sm, md
    'class' => '',
])

@php
    $variantClass = match($variant) {
        'primary' => $outline ? 'border border-primary text-primary bg-primary/10' : 'bg-primary text-primary-foreground',
        'success' => $outline ? 'border border-green-500 text-green-600 bg-green-500/10 dark:text-green-400' : 'bg-green-600 text-white',
        'danger' => $outline ? 'border border-red-500 text-red-600 bg-red-500/10 dark:text-red-400' : 'bg-red-600 text-white',
        'warning' => $outline ? 'border border-amber-500 text-amber-700 bg-amber-500/10 dark:text-amber-400' : 'bg-amber-500 text-white',
        'info' => $outline ? 'border border-sky-500 text-sky-600 bg-sky-500/10 dark:text-sky-400' : 'bg-sky-600 text-white',
        default => $outline ? 'border border-border text-muted-foreground bg-muted/20' : 'bg-secondary text-secondary-foreground',
    };

    $sizeClass = match($size) {
        'sm' => 'px-2 py-0.5 text-2xs font-medium rounded-md gap-1',
        default => 'px-2.5 py-1 text-xs font-medium rounded-lg gap-1.5',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center justify-center font-medium transition-colors $variantClass $sizeClass $class"]) }}>
    {{ $slot }}
</span>

