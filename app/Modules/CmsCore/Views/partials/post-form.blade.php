@php
    /** @var \App\Modules\CmsCore\Models\Post|null $post */
    $post = $post ?? null;
    $selectedCategories = collect(old('category_ids', $post?->categories?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedTags = collect(old('tag_ids', $post?->tags?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedMedia = collect(old('media_ids', $post?->media?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedFeaturedMediaId = old('featured_media_id', $post?->featured_media_id);
    $selectedFeaturedMedia = filled($selectedFeaturedMediaId) ? \App\Modules\CmsCore\Models\Media::query()->find((int) $selectedFeaturedMediaId) : null;
    $seoMeta = $seoMeta ?? ['seo_title' => '', 'seo_description' => '', 'seo_canonical' => ''];
    $mediaMeta = $mediaMeta ?? ['video_url' => '', 'audio_url' => '', 'poster_media_id' => ''];
    $buttonMeta = $buttonMeta ?? ['cta_label' => '', 'cta_url' => '', 'cta2_label' => '', 'cta2_url' => ''];
@endphp

<input name="author_id" type="hidden" value="{{ old('author_id', $post?->author_id ?? (string) auth()->user()?->uuid) }}">

<div class="grid gap-6 lg:grid-cols-2">
    <div class="kt-form-item">
        <label class="kt-form-label">Title <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <input class="kt-input" name="title" required type="text" value="{{ old('title', $post?->title) }}">
        </div>
        @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Slug</label>
        <div class="kt-form-control">
            <input class="kt-input" name="slug" placeholder="auto-generated-if-empty" type="text" value="{{ old('slug', $post?->slug) }}">
        </div>
        @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Post Type <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" name="post_type_id" required>
                <option value="">Select post type</option>
                @foreach($postTypes as $type)
                    <option value="{{ $type->id }}" @selected((int) old('post_type_id', $post?->post_type_id) === $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('post_type_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Status <span class="text-destructive">*</span></label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" name="status" required>
                @foreach($statusOptions as $option)
                    <option value="{{ $option }}" @selected(old('status', $post?->status ?? 'draft') === $option)>
                        {{ ucfirst($option) }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('status') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Published At</label>
        <div class="kt-form-control">
            <input
                class="kt-input"
                name="published_at"
                type="datetime-local"
                value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}"
            >
        </div>
        @error('published_at') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Scheduled For</label>
        <div class="kt-form-control">
            <input
                class="kt-input"
                name="scheduled_for"
                type="datetime-local"
                value="{{ old('scheduled_for', $post?->scheduled_for?->format('Y-m-d\TH:i')) }}"
            >
        </div>
        @error('scheduled_for') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item lg:col-span-2">
        <label class="kt-form-label">Excerpt</label>
        <div class="kt-form-control">
            <textarea class="kt-textarea min-h-[90px]" name="excerpt">{{ old('excerpt', $post?->excerpt) }}</textarea>
        </div>
        @error('excerpt') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <x-tiptap-editor
        name="body"
        :content="old('body', $post?->body ?? '')"
        :upload-url="route('cms-core.media.upload-inline')"
        label="Body"
        :required="true"
    />

    <div class="kt-form-item">
        <label class="kt-form-label">Categories</label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" multiple name="category_ids[]">
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array($category->id, $selectedCategories, true))>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('category_ids') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Tags</label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" multiple name="tag_ids[]">
                @foreach($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(in_array($tag->id, $selectedTags, true))>
                        {{ $tag->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('tag_ids') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Parent Post</label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" name="parent_id">
                <option value="">None</option>
                @foreach($parentPosts as $parentPost)
                    <option value="{{ $parentPost->id }}" @selected((int) old('parent_id', $post?->parent_id) === $parentPost->id)>
                        {{ $parentPost->title }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('parent_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item lg:col-span-2">
        <label class="kt-form-label">Featured Image</label>
        <div class="kt-form-control">
            @include('cms-core::partials.media-picker', ['name' => 'featured_media_id', 'selected' => $selectedFeaturedMedia, 'uploadName' => 'featured_image'])
        </div>
        @error('featured_media_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        @error('featured_image') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Media Attachments</label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" multiple name="media_ids[]">
                @foreach($mediaItems as $mediaItem)
                    <option value="{{ $mediaItem->id }}" @selected(in_array($mediaItem->id, $selectedMedia, true))>
                        {{ $mediaItem->title ?: $mediaItem->original_filename ?: $mediaItem->filename ?: 'Media #'.$mediaItem->id }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('media_ids') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>
</div>

{{-- Embedded Media Section --}}
<div class="mt-8 border-t border-border pt-6">
    <h3 class="mb-4 text-sm font-semibold text-foreground">Embedded Media</h3>
    <p class="mb-4 text-xs text-muted-foreground">Optionally attach a video, audio clip, or poster image to this post. These display prominently on the public page.</p>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="kt-form-item lg:col-span-2">
            <label class="kt-form-label">Video URL</label>
            <div class="kt-form-control">
                <input class="kt-input" name="video_url" type="url" value="{{ old('video_url', $mediaMeta['video_url']) }}" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/...">
            </div>
            <p class="mt-1 text-xs text-muted-foreground">YouTube or Vimeo URL. Embeds automatically on the public page.</p>
            @error('video_url') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Audio URL</label>
            <div class="kt-form-control">
                <input class="kt-input" name="audio_url" type="url" value="{{ old('audio_url', $mediaMeta['audio_url']) }}" placeholder="https://soundcloud.com/... or direct .mp3 link">
            </div>
            @error('audio_url') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Poster Image</label>
            <div class="kt-form-control">
                <select class="kt-select" data-kt-select="true" name="poster_media_id">
                    <option value="">None (uses featured image)</option>
                    @foreach($mediaItems as $mediaItem)
                        <option value="{{ $mediaItem->id }}" @selected((int) old('poster_media_id', $mediaMeta['poster_media_id']) === $mediaItem->id)>
                            {{ $mediaItem->title ?: $mediaItem->original_filename ?: $mediaItem->filename ?: 'Media #'.$mediaItem->id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">Thumbnail for video/audio. Falls back to featured image.</p>
            @error('poster_media_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- Buttons & display order --}}
<div class="mt-8 border-t border-border pt-6">
    <h3 class="mb-4 text-sm font-semibold text-foreground">Buttons and Display Order</h3>
    <p class="mb-4 text-xs text-muted-foreground">Used by homepage slides: up to two buttons shown on the slide, and where the slide sits in the rotation. Leave a button's text empty to hide it.</p>
    <div class="grid gap-6 lg:grid-cols-2">
        @foreach(['cta' => 'First button', 'cta2' => 'Second button'] as $buttonKey => $buttonTitle)
            <div class="kt-form-item">
                <label class="kt-form-label">{{ $buttonTitle }} text</label>
                <div class="kt-form-control">
                    <input class="kt-input" name="{{ $buttonKey }}_label" type="text" maxlength="60" value="{{ old($buttonKey.'_label', $buttonMeta[$buttonKey.'_label']) }}" placeholder="e.g. Learn more">
                </div>
                @error($buttonKey.'_label') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="kt-form-item">
                <label class="kt-form-label">{{ $buttonTitle }} link</label>
                <div class="kt-form-control">
                    <input class="kt-input" name="{{ $buttonKey }}_url" type="text" value="{{ old($buttonKey.'_url', $buttonMeta[$buttonKey.'_url']) }}" placeholder="/about or https://example.org">
                </div>
                <p class="mt-1 text-xs text-muted-foreground">A page on this site (starting with /) or a full web address.</p>
                @error($buttonKey.'_url') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach

        <div class="kt-form-item">
            <label class="kt-form-label">Display order</label>
            <div class="kt-form-control">
                <input class="kt-input" name="sort_order" type="number" step="1" value="{{ old('sort_order', $post?->sort_order ?? 0) }}">
            </div>
            <p class="mt-1 text-xs text-muted-foreground">Lower numbers appear first. Negative numbers are allowed.</p>
            @error('sort_order') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- SEO Section --}}
<div class="mt-8 border-t border-border pt-6">
    <h3 class="mb-4 text-sm font-semibold text-foreground">Search Engine Optimization</h3>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="kt-form-item lg:col-span-2">
            <label class="kt-form-label">SEO Title</label>
            <div class="kt-form-control">
                <input class="kt-input" name="seo_title" type="text" value="{{ old('seo_title', $seoMeta['seo_title']) }}" placeholder="Defaults to post title if empty">
            </div>
            @error('seo_title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="kt-form-item lg:col-span-2">
            <label class="kt-form-label">SEO Description</label>
            <div class="kt-form-control">
                <textarea class="kt-textarea min-h-[70px]" name="seo_description" placeholder="Defaults to post excerpt if empty">{{ old('seo_description', $seoMeta['seo_description']) }}</textarea>
            </div>
            @error('seo_description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="kt-form-item lg:col-span-2">
            <label class="kt-form-label">Canonical URL</label>
            <div class="kt-form-control">
                <input class="kt-input" name="seo_canonical" type="url" value="{{ old('seo_canonical', $seoMeta['seo_canonical']) }}" placeholder="https://">
            </div>
            @error('seo_canonical') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- Navigation Menu & Sub-Menu Attachment --}}
<div class="mt-8 border-t border-border pt-6">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold text-foreground">Menu & Sub-Menu Placement</h3>
            <p class="text-xs text-muted-foreground">Attach this page directly to a public or institutional navigation menu.</p>
        </div>
        @if(!empty($attachedMenuItem))
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary">
                Currently in: {{ $attachedMenuItem->menu?->name ?? 'Menu #'.$attachedMenuItem->menu_id }}
            </span>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="kt-form-item">
            <label class="kt-form-label">Target Menu</label>
            <div class="kt-form-control">
                <select class="kt-select" name="attach_to_menu_id">
                    <option value="">Do not attach to menu</option>
                    @foreach($menus ?? [] as $m)
                        <option value="{{ $m->id }}" @selected((int) old('attach_to_menu_id', $attachedMenuItem?->menu_id) === $m->id)>
                            {{ $m->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @error('attach_to_menu_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Parent Menu Item (Sub-Menu)</label>
            <div class="kt-form-control">
                <select class="kt-select" name="parent_menu_item_id">
                    <option value="">Top-Level Item</option>
                    @foreach($menus ?? [] as $m)
                        <optgroup label="{{ $m->name }}">
                            @foreach($m->items ?? [] as $item)
                                @if(empty($item->parent_id))
                                    <option value="{{ $item->id }}" @selected((int) old('parent_menu_item_id', $attachedMenuItem?->parent_id) === $item->id)>
                                        {{ $item->label }}
                                    </option>
                                @endif
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">Select a top-level parent to nest as a dropdown sub-menu item.</p>
            @error('parent_menu_item_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Menu Item Label</label>
            <div class="kt-form-control">
                <input class="kt-input" name="menu_item_label" type="text" value="{{ old('menu_item_label', $attachedMenuItem?->label) }}" placeholder="Leave empty to use Post Title">
            </div>
            @error('menu_item_label') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

{{-- Visual Page Builder Blocks --}}
<div class="mt-8 border-t border-border pt-6" x-data="{
    blocks: {{ json_encode(old('blocks_json', $post?->blocks_json ?? [])) }},
    addBlock(type) {
        if (type === 'hero') {
            this.blocks.push({ type: 'hero', data: { title: 'Headline Here', subtitle: 'Supporting narrative and value proposition', cta_label: 'Explore', cta_url: '/site/properties' } });
        } else if (type === 'feature_cards') {
            this.blocks.push({ type: 'feature_cards', data: { items: [{ title: 'Service Feature 1', description: 'Detailed capability breakdown and guarantee', icon: 'check' }] } });
        } else if (type === 'stats_grid') {
            this.blocks.push({ type: 'stats_grid', data: { items: [{ label: 'Completed Deliverables', value: '150', suffix: '+' }] } });
        } else if (type === 'faq_accordion') {
            this.blocks.push({ type: 'faq_accordion', data: { items: [{ question: 'What is the required documentation?', answer: 'Official photo ID, land lease consent, and proof of address.' }] } });
        } else if (type === 'cta_banner') {
            this.blocks.push({ type: 'cta_banner', data: { headline: 'Ready to Acquire Your Plot?', description: 'Contact our certified land acquisition team today.', button_text: 'Inquire Now', button_url: '/site/properties' } });
        } else if (type === 'property_listings_grid') {
            this.blocks.push({ type: 'property_listings_grid', data: { title: 'Featured Land Parcels', limit: 6 } });
        }
    },
    removeBlock(index) {
        this.blocks.splice(index, 1);
    }
}">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-sm font-semibold text-foreground">Visual Block Page Builder</h3>
            <p class="text-xs text-muted-foreground">Construct modular landing pages with sanitized layout components.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="addBlock('hero')" class="kt-btn kt-btn-xs kt-btn-outline">+ Hero Banner</button>
            <button type="button" @click="addBlock('feature_cards')" class="kt-btn kt-btn-xs kt-btn-outline">+ Features Grid</button>
            <button type="button" @click="addBlock('stats_grid')" class="kt-btn kt-btn-xs kt-btn-outline">+ Stats Counters</button>
            <button type="button" @click="addBlock('faq_accordion')" class="kt-btn kt-btn-xs kt-btn-outline">+ FAQ Accordion</button>
            <button type="button" @click="addBlock('cta_banner')" class="kt-btn kt-btn-xs kt-btn-outline">+ CTA Banner</button>
            <button type="button" @click="addBlock('property_listings_grid')" class="kt-btn kt-btn-xs kt-btn-outline">+ Property Grid</button>
        </div>
    </div>

    {{-- Rendered Blocks List --}}
    <div class="space-y-4">
        <template x-for="(block, idx) in blocks" :key="idx">
            <div class="p-4 bg-muted/40 rounded-xl border border-border">
                <div class="flex items-center justify-between mb-3 border-b border-border/60 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-primary" x-text="'Block #' + (idx + 1) + ': ' + block.type"></span>
                    <button type="button" @click="removeBlock(idx)" class="text-xs text-destructive hover:underline">Remove Block</button>
                </div>

                {{-- Hero Block Inputs --}}
                <div x-if="block.type === 'hero'" class="grid gap-3 lg:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium text-foreground">Hero Headline</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][title]'" x-model="block.data.title">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">Subtitle</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][subtitle]'" x-model="block.data.subtitle">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">CTA Button Label</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][cta_label]'" x-model="block.data.cta_label">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">CTA Target URL</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][cta_url]'" x-model="block.data.cta_url">
                    </div>
                    <input type="hidden" :name="'blocks_json[' + idx + '][type]'" :value="block.type">
                </div>

                {{-- CTA Banner Inputs --}}
                <div x-if="block.type === 'cta_banner'" class="grid gap-3 lg:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium text-foreground">Banner Headline</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][headline]'" x-model="block.data.headline">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">Banner Description</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][description]'" x-model="block.data.description">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">Button Text</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][button_text]'" x-model="block.data.button_text">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">Button Link</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][button_url]'" x-model="block.data.button_url">
                    </div>
                    <input type="hidden" :name="'blocks_json[' + idx + '][type]'" :value="block.type">
                </div>

                {{-- Property Grid Inputs --}}
                <div x-if="block.type === 'property_listings_grid'" class="grid gap-3 lg:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium text-foreground">Section Title</label>
                        <input class="kt-input mt-1" type="text" :name="'blocks_json[' + idx + '][data][title]'" x-model="block.data.title">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-foreground">Display Limit</label>
                        <input class="kt-input mt-1" type="number" min="1" max="20" :name="'blocks_json[' + idx + '][data][limit]'" x-model="block.data.limit">
                    </div>
                    <input type="hidden" :name="'blocks_json[' + idx + '][type]'" :value="block.type">
                </div>

                {{-- Generic hidden type for other blocks --}}
                <div x-if="!['hero', 'cta_banner', 'property_listings_grid'].includes(block.type)">
                    <p class="text-xs text-muted-foreground" x-text="'Block configured with ' + (block.data.items ? block.data.items.length : 1) + ' items.'"></p>
                    <input type="hidden" :name="'blocks_json[' + idx + '][type]'" :value="block.type">
                </div>
            </div>
        </template>

        <div x-show="blocks.length === 0" class="p-6 text-center border border-dashed border-border rounded-xl text-xs text-muted-foreground">
            No visual blocks added. Use the buttons above to build a block-based landing page or continue with standard rich text body.
        </div>
    </div>
</div>
