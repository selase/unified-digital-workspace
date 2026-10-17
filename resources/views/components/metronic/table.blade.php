@props([
    'class' => '',
])

<div class="kt-card-table kt-scrollable-x-auto border border-border rounded-xl bg-card overflow-hidden shadow-xs {{ $class }}">
    <table {{ $attributes->merge(['class' => 'kt-table table-auto w-full text-left text-sm text-foreground']) }}>
        @if(isset($head))
            <thead class="border-b border-border bg-muted/40 font-semibold text-xs uppercase tracking-wider text-muted-foreground">
                {{ $head }}
            </thead>
        @endif
        <tbody class="divide-y divide-border font-normal text-sm">
            {{ $slot }}
        </tbody>
    </table>
    @if(isset($pagination))
        <div class="px-5 py-4 border-t border-border bg-muted/10">
            {{ $pagination }}
        </div>
    @endif
</div>

