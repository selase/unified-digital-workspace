<section class="py-14" style="background: var(--tgf-primary);">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-6">
            <div>
                <h2 class="text-xl font-bold text-white">Want to support thyroid health in Ghana?</h2>
                <p class="mt-1 text-teal-100">Your contribution makes early detection and treatment possible.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $cmsUrl->route('pages.show', 'donate') }}" class="tgf-btn-accent">Donate now</a>
                <a href="{{ $cmsUrl->route('pages.show', 'volunteer') }}" class="rounded-md border-2 border-white px-6 py-3 text-sm font-semibold text-white transition hover:bg-white hover:text-teal-700">Volunteer</a>
            </div>
        </div>
    </div>
</section>
