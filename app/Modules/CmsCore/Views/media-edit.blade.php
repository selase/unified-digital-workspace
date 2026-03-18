@extends('layouts.metronic.app')

@section('title', 'Edit Media')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core / Media</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Metadata</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $media->original_filename }} &middot; {{ $media->mime_type }}</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.media.show', $media) }}">Back to Details</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('cms-core.media.update', $media) }}" class="kt-form" method="POST">
                @csrf
                @method('PUT')

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Title</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="title" type="text" value="{{ old('title', $media->title) }}">
                        </div>
                        @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Alt Text</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="alt_text" type="text" value="{{ old('alt_text', $media->alt_text) }}">
                        </div>
                        @error('alt_text') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Caption</label>
                        <div class="kt-form-control">
                            <textarea class="kt-textarea min-h-[60px]" name="caption">{{ old('caption', $media->caption) }}</textarea>
                        </div>
                        @error('caption') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea class="kt-textarea min-h-[90px]" name="description">{{ old('description', $media->description) }}</textarea>
                        </div>
                        @error('description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label flex items-center gap-2">
                            <input class="kt-checkbox" name="is_public" type="checkbox" value="1" @checked(old('is_public', $media->is_public))>
                            Publicly accessible
                        </label>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.media.show', $media) }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
@endsection
