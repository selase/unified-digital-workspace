@extends('layouts.metronic.app')

@section('title', 'Upload Media')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Upload Media</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Upload images, documents, and other assets to the media library.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.media.index') }}">Back to Library</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form action="{{ route('cms-core.media.store') }}" class="kt-form" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">File <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input class="kt-input file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20" name="file" type="file" required>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Max 10MB. Images, documents, audio, and video files accepted.</p>
                        @error('file') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Title</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="title" type="text" value="{{ old('title') }}" placeholder="Auto-generated from filename if empty">
                        </div>
                        @error('title') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Alt Text</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="alt_text" type="text" value="{{ old('alt_text') }}" placeholder="Describe the image for accessibility">
                        </div>
                        @error('alt_text') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea class="kt-textarea min-h-[90px]" name="description">{{ old('description') }}</textarea>
                        </div>
                        @error('description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label flex items-center gap-2">
                            <input class="kt-checkbox" name="is_public" type="checkbox" value="1" @checked(old('is_public', true))>
                            Publicly accessible
                        </label>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.media.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">Upload</button>
                </div>
            </form>
        </div>
    </section>
@endsection
