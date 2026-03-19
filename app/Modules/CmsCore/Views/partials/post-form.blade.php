@php
    /** @var \App\Modules\CmsCore\Models\Post|null $post */
    $post = $post ?? null;
    $selectedCategories = collect(old('category_ids', $post?->categories?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedTags = collect(old('tag_ids', $post?->tags?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedMedia = collect(old('media_ids', $post?->media?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedFeaturedMediaId = old('featured_media_id', $post?->featured_media_id);
    $seoMeta = $seoMeta ?? ['seo_title' => '', 'seo_description' => '', 'seo_canonical' => ''];
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

    <div class="kt-form-item lg:col-span-2" x-data="tiptapEditor(@js(old('body', $post?->body ?? '')), @js(route('cms-core.media.upload-inline')))">
        <label class="kt-form-label">Body <span class="text-destructive">*</span></label>
        <input type="hidden" name="body" x-model="content">

        {{-- Toolbar — @mousedown.prevent keeps focus in the ProseMirror editor --}}
        <div class="flex flex-wrap items-center gap-1 rounded-t-lg bg-muted/30 px-2 py-1.5">
            <button type="button" @mousedown.prevent @click="toggleBold()" :class="isActive('bold') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-bold hover:bg-muted/50" title="Bold">B</button>
            <button type="button" @mousedown.prevent @click="toggleItalic()" :class="isActive('italic') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs italic hover:bg-muted/50" title="Italic">I</button>
            <button type="button" @mousedown.prevent @click="toggleStrike()" :class="isActive('strike') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs line-through hover:bg-muted/50" title="Strikethrough">S</button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" @mousedown.prevent @click="toggleHeading(2)" :class="isActive('heading', {level: 2}) ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-semibold hover:bg-muted/50" title="Heading 2">H2</button>
            <button type="button" @mousedown.prevent @click="toggleHeading(3)" :class="isActive('heading', {level: 3}) ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-semibold hover:bg-muted/50" title="Heading 3">H3</button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" @mousedown.prevent @click="toggleBulletList()" :class="isActive('bulletList') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Bullet List">&bull; List</button>
            <button type="button" @mousedown.prevent @click="toggleOrderedList()" :class="isActive('orderedList') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Ordered List">1. List</button>
            <button type="button" @mousedown.prevent @click="toggleBlockquote()" :class="isActive('blockquote') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Blockquote">&ldquo; Quote</button>
            <button type="button" @mousedown.prevent @click="toggleCode()" :class="isActive('code') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs font-mono hover:bg-muted/50" title="Inline Code">&lt;/&gt;</button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" @mousedown.prevent @click="setLink()" :class="isActive('link') ? 'bg-primary/10 text-primary' : 'text-muted-foreground'" class="rounded px-2 py-1 text-xs hover:bg-muted/50" title="Add Link">Link</button>
            <button type="button" @mousedown.prevent @click="addImage()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Upload Image" :disabled="uploading">
                <span x-show="!uploading">Image</span>
                <span x-show="uploading" x-cloak>Uploading...</span>
            </button>
            <button type="button" @mousedown.prevent @click="setHorizontalRule()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Horizontal Rule">&mdash;</button>
            <span class="mx-1 h-4 w-px bg-border"></span>
            <button type="button" @mousedown.prevent @click="undo()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Undo">Undo</button>
            <button type="button" @mousedown.prevent @click="redo()" class="rounded px-2 py-1 text-xs text-muted-foreground hover:bg-muted/50" title="Redo">Redo</button>
        </div>

        {{-- Editor area --}}
        <div class="kt-form-control">
            <div x-ref="editor" class="kt-textarea min-h-[220px] !p-0"></div>
        </div>
        @error('body') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

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

    <div class="kt-form-item">
        <label class="kt-form-label">Featured Media</label>
        <div class="kt-form-control">
            <select class="kt-select" data-kt-select="true" name="featured_media_id">
                <option value="">None</option>
                @foreach($mediaItems as $mediaItem)
                    <option value="{{ $mediaItem->id }}" @selected((int) $selectedFeaturedMediaId === $mediaItem->id)>
                        {{ $mediaItem->title ?: $mediaItem->original_filename ?: $mediaItem->filename ?: 'Media #'.$mediaItem->id }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('featured_media_id') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="kt-form-item">
        <label class="kt-form-label">Upload Featured Image</label>
        <div class="kt-form-control">
            <input class="kt-input file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20" name="featured_image" type="file" accept="image/*">
        </div>
        <p class="mt-1 text-xs text-muted-foreground">JPEG, PNG, WebP up to 10MB. Upload overrides selected featured media.</p>
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
