@props([
    'title' => null,
    'description' => null,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'kt-card border border-border shadow-xs rounded-xl bg-card text-card-foreground ' . $class]) }}>
    @if($title || isset($header) || isset($toolbar) || isset($headerActions))
        <div class="kt-card-header flex items-center justify-between px-5 lg:px-7 py-4 border-b border-border">
            @if(isset($header))
                {{ $header }}
            @else
                <div class="flex flex-col gap-0.5">
                    <h3 class="text-base font-semibold text-foreground tracking-tight">
                        {{ $title }}
                    </h3>
                    @if($description)
                        <p class="text-xs text-muted-foreground">
                            {{ $description }}
                        </p>
                    @endif
                </div>
                @if(isset($toolbar) || isset($headerActions))
                    <div class="flex items-center gap-2">
                        {{ $toolbar ?? $headerActions }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    <div class="kt-card-body kt-card-content p-5 lg:p-7">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="kt-card-footer px-5 lg:px-7 py-4 border-t border-border bg-muted/20 rounded-b-xl">
            {{ $footer }}
        </div>
    @endif
</div>

