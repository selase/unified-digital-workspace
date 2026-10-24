<style>
    .brochure-body img, .cms-page-body img { max-width: 100%; height: auto; border-radius: 0.5rem; margin-inline: auto; }
    .brochure-body div:has(> figure), .cms-page-body div:has(> figure) { display: flex; flex-wrap: wrap; gap: 1.25rem; justify-content: center; margin: 2rem 0; }
    .brochure-body figure, .cms-page-body figure { flex: 1 1 220px; max-width: 320px; margin: 0; }
    .brochure-body figure img, .cms-page-body figure img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
    .brochure-body figcaption, .cms-page-body figcaption { margin-top: 0.5rem; text-align: center; font-size: 0.9rem; }
    .brochure-body p:has(> img + img), .cms-page-body p:has(> img + img) { display: flex; flex-wrap: wrap; gap: 1.25rem; justify-content: center; align-items: center; }
    .brochure-body p > img[alt]:only-child, .cms-page-body p > img[alt]:only-child { max-height: 460px; width: auto; }
    .brochure-body h3 + p:has(> img) img, .cms-page-body h3 + p:has(> img) img { height: 56px; width: auto; border-radius: 0; }
    .brochure-body a, .cms-page-body a { color: var(--brand-color); text-decoration: underline; }
</style>
