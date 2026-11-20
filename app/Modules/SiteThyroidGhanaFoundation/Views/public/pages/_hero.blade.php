@php
    $heroMedia = $heroMedia ?? (isset($slug) ? \App\Modules\SiteThyroidGhanaFoundation\Support\PageHero::forSlug($slug) : null);
    $heroMedia = \App\Modules\SiteThyroidGhanaFoundation\Support\PageHero::suits($heroMedia) ? $heroMedia : null;
    $heroSources = $heroMedia ? \App\Modules\SiteThyroidGhanaFoundation\Support\PageHero::sources($heroMedia) : null;
@endphp
<section class="relative isolate overflow-hidden" style="background: linear-gradient(135deg, var(--tgf-dark) 0%, #134E4A 100%);">
    @if($heroSources)
        <img class="tgf-hero-image absolute inset-0 -z-20 h-full w-full object-cover" style="object-position: 50% 18%;" src="{{ $heroSources['src'] }}" srcset="{{ $heroSources['srcset'] }}" sizes="100vw" alt="{{ $heroMedia->alt_text ?? '' }}" fetchpriority="high" />
        <div class="absolute inset-0 -z-10" style="background: linear-gradient(90deg, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.6) 55%, rgba(15,23,42,0.35) 100%);"></div>
    @endif
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
        <nav class="mb-6 flex items-center gap-2 text-sm text-slate-300" aria-label="Breadcrumb">
            <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-white">Home</a>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white">{{ $crumb }}</span>
        </nav>
        <h1 class="text-3xl font-extrabold text-white sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        @if(! empty($intro))
            <p class="mt-4 max-w-2xl text-lg text-slate-200">{{ $intro }}</p>
        @endif
    </div>
</section>
