@props([
    'title' => '',
    'subtitle' => '',
    'image' => null,
    'ctaText' => null,
    'ctaUrl' => null,
])

<section class="relative overflow-hidden bg-gray-900 py-20 sm:py-28">
    @if($image)
        <img
            src="{{ $image }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover opacity-30"
        />
    @endif

    <div class="relative mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
        @if($title)
            <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                {{ $title }}
            </h1>
        @endif

        @if($subtitle)
            <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-300">
                {{ $subtitle }}
            </p>
        @endif

        @if($ctaText && $ctaUrl)
            <div class="mt-8">
                <a
                    href="{{ $ctaUrl }}"
                    class="inline-flex items-center rounded-md px-6 py-3 text-sm font-semibold text-white shadow-sm transition"
                    style="background-color: var(--brand-color);"
                >
                    {{ $ctaText }}
                </a>
            </div>
        @endif
    </div>
</section>
