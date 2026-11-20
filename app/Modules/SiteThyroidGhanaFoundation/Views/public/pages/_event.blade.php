<article class="mt-6 flex flex-col gap-4 rounded-2xl bg-white p-6 shadow-sm transition hover:shadow-lg sm:flex-row sm:items-center">
    <div class="flex h-20 w-20 shrink-0 flex-col items-center justify-center rounded-xl text-white" style="background: var(--tgf-primary);">
        <span class="text-2xl font-extrabold leading-none">{{ $event['date']->format('j') }}</span>
        <span class="mt-1 text-xs font-semibold uppercase tracking-wider">{{ $event['date']->format('M Y') }}</span>
    </div>
    <div class="min-w-0">
        <h3 class="text-lg font-bold" style="color: var(--tgf-dark);">
            {{ $event['post']->title }}
        </h3>
        @if($event['location'])
            <p class="mt-1 text-sm" style="color: var(--tgf-muted);">{{ $event['location'] }}</p>
        @endif
        @if($event['post']->excerpt)
            <p class="mt-2 text-sm leading-relaxed" style="color: var(--tgf-text);">{{ $event['post']->excerpt }}</p>
        @endif
        @if($event['registerUrl'] && $event['date']->greaterThanOrEqualTo(today()))
            <a href="{{ $event['registerUrl'] }}" class="tgf-btn-accent mt-4 inline-block" rel="noopener">Register</a>
        @endif
    </div>
</article>
