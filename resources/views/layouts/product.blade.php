<!doctype html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', config('product-page.brand.name'))</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    @livewireStyles
    @stack('styles')
</head>

<body class="bg-[#0b0f0e] text-white font-['Instrument_Sans'] selection:bg-white/10">
    {{-- Background glow --}}
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[#0b0f0e]"></div>
        <div
            class="absolute -top-40 left-1/2 h-[700px] w-[900px] -translate-x-1/2 rounded-full bg-blue-500/10 blur-3xl">
        </div>
        <div class="absolute top-40 -left-40 h-[700px] w-[900px] rounded-full bg-emerald-500/10 blur-3xl"></div>
        <div class="absolute top-52 -right-40 h-[700px] w-[900px] rounded-full bg-blue-500/10 blur-3xl"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-black/40 to-black"></div>
    </div>

    {{-- NAV --}}
    <header class="sticky top-0 z-30 border-b border-white/5 bg-black/20 backdrop-blur">
        <div class="mx-auto max-w-7xl px-6">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('product.template') }}" class="flex items-center gap-3">
                        <div
                            class="grid h-8 w-8 place-items-center rounded-lg border border-white/10 bg-white/5 text-xs font-medium">
                            {{ config('product-page.brand.logo_text') }}
                        </div>
                        <span class="text-sm font-medium text-white/90">{{ config('product-page.brand.name') }}</span>
                    </a>
                </div>

                <nav class="hidden items-center gap-8 md:flex">
                    @foreach (config('product-page.nav.links') as $l)
                        <a href="{{ $l['href'] }}"
                            class="text-sm {{ request()->url() == $l['href'] ? 'text-white font-medium' : 'text-white/70 hover:text-white' }} transition">
                            {{ $l['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ config('product-page.nav.cta_secondary.href') }}"
                        class="hidden rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/80 hover:bg-white/10 md:inline-flex transition">
                        {{ config('product-page.nav.cta_secondary.label') }}
                    </a>
                    <a href="{{ config('product-page.nav.cta_primary.href') }}"
                        class="inline-flex rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white shadow-sm shadow-blue-600/20 hover:bg-blue-500 transition">
                        {{ config('product-page.nav.cta_primary.label') }}
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    @section('footer')
    <footer class="border-t border-white/5 pt-10 pb-20 mt-20">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid gap-10 md:grid-cols-5">
                <div class="md:col-span-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-xs font-medium text-blue-400">
                            {{ config('product-page.brand.logo_text') }}
                        </div>
                        <div>
                            <div class="text-sm font-medium text-white/90">{{ config('product-page.brand.name') }}</div>
                            <div class="text-xs text-white/50">{{ config('product-page.footer.tagline') }}</div>
                        </div>
                    </div>
                    <div class="mt-6 text-xs text-white/40">
                        © {{ date('Y') }} {{ config('product-page.brand.name') }} · All rights reserved.
                    </div>
                </div>

                @foreach (config('product-page.footer.columns') as $colTitle => $links)
                    <div>
                        <div class="text-xs font-medium text-white/60">{{ $colTitle }}</div>
                        <div class="mt-4 space-y-2">
                            @foreach ($links as $l)
                                <a href="{{ $l['href'] }}" class="block text-sm text-white/50 hover:text-white/80 transition">
                                    {{ $l['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </footer>
    @show

    @livewireScripts
    @stack('scripts')
</body>

</html>