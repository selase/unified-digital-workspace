@props([
    'title' => '',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div class="flex flex-wrap items-center justify-between gap-5 pb-7">
    <div class="flex flex-col justify-center gap-2">
        <h1 class="text-xl font-semibold leading-none text-foreground tracking-tight">
            {{ $title }}
        </h1>
        <div class="flex items-center flex-wrap gap-1.5 text-xs font-medium">
            @if(!empty($breadcrumbs))
                @foreach($breadcrumbs as $breadcrumb)
                    @if(isset($breadcrumb['url']) && !$loop->last)
                        <a class="text-muted-foreground hover:text-primary transition-colors" href="{{ $breadcrumb['url'] }}">
                            {{ $breadcrumb['label'] }}
                        </a>
                        <span class="text-muted-foreground/40">/</span>
                    @else
                        <span class="text-foreground font-medium">
                            {{ $breadcrumb['label'] ?? $breadcrumb }}
                        </span>
                    @endif
                @endforeach
            @elseif($subtitle)
                <span class="text-muted-foreground">
                    {{ $subtitle }}
                </span>
            @endif
        </div>
    </div>
    @if(isset($actions))
        <div class="flex items-center gap-2.5">
            {{ $actions }}
        </div>
    @endif
</div>

