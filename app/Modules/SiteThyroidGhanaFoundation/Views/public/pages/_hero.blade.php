<section style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
        <nav class="mb-6 flex items-center gap-2 text-sm text-slate-400" aria-label="Breadcrumb">
            <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white">{{ $crumb }}</span>
        </nav>
        <h1 class="text-3xl font-extrabold text-white sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-slate-300">{{ $intro }}</p>
    </div>
</section>
