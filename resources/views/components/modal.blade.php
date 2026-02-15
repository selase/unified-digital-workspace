@props([
    'type' => 'md'
])

@php
    $maxWidth = match ($type) {
        'sm' => 'max-w-sm',
        'lg' => 'max-w-3xl',
        'xl' => 'max-w-5xl',
        default => 'max-w-xl',
    };
@endphp

<div class="kt-modal" data-kt-modal="true" id="{{ $id }}">
    <div class="kt-modal-content {{ $maxWidth }} top-9">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">{{ $title }}</h3>

            <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" data-kt-modal-dismiss="true" type="button">
                <i class="ki-filled ki-cross"></i>
            </button>
        </div>

        <div class="p-6">
            {{ $slot }}
        </div>
    </div>
</div>
