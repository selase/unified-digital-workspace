@php
    /** @var \App\Modules\CmsCore\Models\Post|null $post */
    $post = $post ?? null;
    $selectedCategories = collect(old('category_ids', $post?->categories?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedTags = collect(old('tag_ids', $post?->tags?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedMedia = collect(old('media_ids', $post?->media?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $selectedFeaturedMediaId = old('featured_media_id', $post?->featured_media_id);
    $seoMeta = $seoMeta ?? ['seo_title' => '', 'seo_description' => '', 'seo_canonical' => ''];
    $mediaMeta = $mediaMeta ?? ['video_url' => '', 'audio_url' => '', 'poster_media_id' => ''];
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
