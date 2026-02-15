<div class="rounded-lg border border-border bg-muted/20 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-base font-semibold text-foreground">{{ $label }}</h3>
        @if ($config)
            <span class="kt-badge kt-badge-outline kt-badge-success">{{ __('Configured') }}</span>
        @else
            <span class="kt-badge kt-badge-outline">{{ __('Not Configured') }}</span>
        @endif
    </div>

    <div class="mt-4 grid gap-4">
        <input type="hidden" name="configs[{{ $provider }}][provider]" value="{{ $provider }}">

        <div class="grid gap-1">
            <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ $label }} API Key</label>
            <input
                type="password"
                name="configs[{{ $provider }}][api_key]"
                class="kt-input"
                placeholder="{{ $config ? 'Current key set (leave empty to keep)' : 'sk-...' }}"
                value=""
            />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-foreground">
                <input class="kt-switch" type="checkbox" name="configs[{{ $provider }}][is_active]" value="1" {{ ($config && $config->is_active) || ! $config ? 'checked' : '' }} />
                <span>{{ __('Active') }}</span>
            </label>

            @if ($config)
                <div class="flex items-center gap-3 text-xs text-muted-foreground">
                    <span>{{ __('Last updated: :date', ['date' => $config->updated_at->diffForHumans()]) }}</span>
                    <a
                        href="{{ route('tenant.llm-config.destroy', $provider) }}"
                        class="kt-btn kt-btn-sm kt-btn-outline kt-btn-danger"
                        onclick="event.preventDefault(); document.getElementById('delete-config-{{ $provider }}').submit();"
                    >
                        {{ __('Remove') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($config)
    <form id="delete-config-{{ $provider }}" action="{{ route('tenant.llm-config.destroy', $provider) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
