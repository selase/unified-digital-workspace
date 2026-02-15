@props([
    'width' => '700px',
    'direction' => 'end',
])

<!-- {{ $title.' drawer' }} -->
<div
    id="{{ $id }}"

    class="bg-background text-foreground"
    data-kt-drawer="true"
    data-kt-drawer-activate="true"
    data-kt-drawer-toggle="#{{ $id }}_button"
    data-kt-drawer-close="#{{ $id }}_close"
    data-kt-drawer-overlay="true"
    data-kt-drawer-width="{{ $width }}"
    data-kt-drawer-direction="{{ $direction }}"
>
    <div class="flex h-full w-full flex-col rounded-none border-s border-border bg-background">
        <div class="flex items-center justify-between border-b border-border px-5 py-4">
            <h3 class="text-base font-semibold text-foreground">{{ $title }}</h3>
            <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" id="{{ $id }}_close" type="button">
                <x-svg-icon-close />
            </button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            {{ $slot }}
        </div>
    </div>
</div>
