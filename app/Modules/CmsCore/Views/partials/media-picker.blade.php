@php
    /**
     * Image field with a live preview and a searchable library dialog.
     *
     * The library is fetched a page at a time, so it stays fast however many
     * images an organisation has uploaded.
     *
     * @var string $name                                    Name of the hidden input holding the chosen media id
     * @var \App\Modules\CmsCore\Models\Media|null $selected  Currently chosen image
     * @var string|null $uploadName                         Name of a file input for uploading a new image with the form
     */
    $selected = $selected ?? null;
    $uploadName = $uploadName ?? null;
    $selectedTitle = $selected ? ($selected->title ?: $selected->original_filename ?: $selected->filename) : '';
@endphp

<div
    data-media-picker
    data-endpoint="{{ route('cms-core.media.picker') }}"
    class="rounded-lg bg-muted/30 p-4"
>
    <input type="hidden" name="{{ $name }}" value="{{ $selected?->id }}" data-media-picker-input>

    <div class="flex flex-wrap items-start gap-4">
        <div class="flex h-32 w-48 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted/50">
            <img
                data-media-picker-preview
                src="{{ $selected?->url() }}"
                alt="{{ $selected?->alt_text ?: $selectedTitle }}"
                class="h-full w-full object-cover"
                @if(! $selected) hidden @endif
            >
            <span data-media-picker-empty class="px-3 text-center text-xs text-muted-foreground" @if($selected) hidden @endif>No image chosen</span>
        </div>

        <div class="min-w-0 flex-1">
            <p data-media-picker-title class="truncate text-sm font-medium text-foreground">{{ $selectedTitle ?: 'No image chosen' }}</p>
            <p data-media-picker-note class="mt-1 text-xs text-muted-foreground">Shown at the top of the post and on homepage slides.</p>

            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-media-picker-open>Choose from library</button>
                @if($uploadName)
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-media-picker-upload>Upload new image</button>
                    <input type="file" name="{{ $uploadName }}" accept="image/*" class="sr-only" tabindex="-1" data-media-picker-file>
                @endif
                <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost" data-media-picker-clear @if(! $selected) hidden @endif>Remove</button>
            </div>
            @if($uploadName)
                <p class="mt-2 text-xs text-muted-foreground">JPEG, PNG or WebP up to 10MB. A new upload is saved when you save the post.</p>
            @endif
        </div>
    </div>

    <dialog data-media-picker-dialog class="m-auto w-[min(64rem,calc(100vw-2rem))] rounded-xl bg-background p-0 text-foreground shadow-xl backdrop:bg-black/50">
        <div class="flex max-h-[85vh] flex-col">
            <div class="flex flex-wrap items-center gap-3 p-5">
                <h3 class="text-base font-semibold text-foreground">Choose an image</h3>
                <input type="search" class="kt-input ms-auto w-full sm:w-72" placeholder="Search by title or file name" aria-label="Search images" data-media-picker-search>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost" data-media-picker-close>Close</button>
            </div>

            <div class="grow overflow-y-auto px-5 pb-5">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" data-media-picker-grid></div>
                <p class="py-8 text-center text-sm text-muted-foreground" data-media-picker-status hidden></p>
                <div class="mt-4 flex justify-center">
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-media-picker-more hidden>Load more</button>
                </div>
            </div>
        </div>
    </dialog>
</div>

@once
    @push('custom-scripts')
        <script>
            document.querySelectorAll('[data-media-picker]').forEach(function (picker) {
                const find = (name) => picker.querySelector('[data-media-picker-' + name + ']');
                const input = find('input'), preview = find('preview'), empty = find('empty'), title = find('title');
                const clear = find('clear'), dialog = find('dialog'), grid = find('grid'), status = find('status');
                const more = find('more'), search = find('search'), file = find('file');
                let page = 1, term = '', request = 0, searchTimer = null;

                function show(url, label) {
                    preview.src = url;
                    preview.alt = label;
                    preview.hidden = false;
                    empty.hidden = true;
                    clear.hidden = false;
                    title.textContent = label;
                }

                function choose(item) {
                    input.value = item.id;
                    if (file) { file.value = ''; }
                    show(item.url, item.title);
                    dialog.close();
                }

                function tile(item) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'group overflow-hidden rounded-lg bg-muted/30 text-start transition hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-primary';
                    if (String(item.id) === input.value) { button.setAttribute('aria-current', 'true'); button.classList.add('ring-2', 'ring-primary'); }

                    // An image whose file is gone cannot be used, so its tile is dropped rather than shown broken.
                    const image = document.createElement('img');
                    image.src = item.thumb || item.url;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.className = 'aspect-video w-full object-cover';
                    image.addEventListener('error', () => {
                        button.remove();
                        if (! grid.children.length && more.hidden) { status.hidden = false; }
                    });

                    // The clamp sits on an inner element: on the padded one, part of a third line shows in the padding.
                    const caption = document.createElement('span');
                    caption.className = 'block px-2.5 py-2';
                    const captionText = document.createElement('span');
                    captionText.className = 'line-clamp-2 min-h-8 text-xs leading-4 text-foreground';
                    captionText.textContent = item.title;
                    caption.append(captionText);
                    button.title = item.title;

                    button.append(image, caption);
                    button.addEventListener('click', () => choose(item));

                    return button;
                }

                function load(reset) {
                    if (reset) { page = 1; grid.replaceChildren(); }
                    const current = ++request;
                    status.hidden = false;
                    status.textContent = 'Loading…';
                    more.hidden = true;

                    const url = new URL(picker.dataset.endpoint, window.location.origin);
                    url.searchParams.set('page', page);
                    if (term) { url.searchParams.set('search', term); }

                    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                        .then((response) => { if (! response.ok) { throw new Error(response.status); } return response.json(); })
                        .then((result) => {
                            if (current !== request) { return; }
                            result.data.forEach((item) => grid.append(tile(item)));
                            status.hidden = grid.children.length > 0;
                            status.textContent = term ? 'No images match your search.' : 'No images have been uploaded yet.';
                            more.hidden = ! result.has_more;
                        })
                        .catch(() => {
                            if (current !== request) { return; }
                            status.hidden = false;
                            status.textContent = 'Could not load images. Please try again.';
                        });
                }

                find('open').addEventListener('click', () => { dialog.showModal(); load(true); search.focus(); });
                find('close').addEventListener('click', () => dialog.close());
                dialog.addEventListener('click', (event) => { if (event.target === dialog) { dialog.close(); } });
                more.addEventListener('click', () => { page += 1; load(false); });
                search.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => { term = search.value.trim(); load(true); }, 250);
                });
                search.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); } });

                clear.addEventListener('click', () => {
                    input.value = '';
                    if (file) { file.value = ''; }
                    preview.hidden = true;
                    preview.removeAttribute('src');
                    empty.hidden = false;
                    clear.hidden = true;
                    title.textContent = 'No image chosen';
                });

                if (file) {
                    find('upload').addEventListener('click', () => file.click());
                    file.addEventListener('change', () => {
                        const chosen = file.files[0];
                        if (! chosen) { return; }
                        show(URL.createObjectURL(chosen), chosen.name + ' (uploads when you save)');
                    });
                }
            });
        </script>
    @endpush
@endonce
