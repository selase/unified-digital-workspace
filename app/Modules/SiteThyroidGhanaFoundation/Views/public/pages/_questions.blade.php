<section class="py-16 lg:py-20">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold" style="color: var(--tgf-dark);">Questions?</h2>
        <p class="mt-2 text-sm" style="color: var(--tgf-muted);">Prefer to talk? Call <a href="tel:+233243376304" class="font-semibold underline" style="color: var(--tgf-primary-dark);">+233 (024) 337 6304</a> or email <a href="mailto:info@thyroidghanafoundation.org?subject={{ rawurlencode($subject) }}" class="font-semibold underline" style="color: var(--tgf-primary-dark);">info@thyroidghanafoundation.org</a>.</p>
        @include('cms-core::components.enquiry-form', ['type' => $type])
    </div>
</section>
