@extends('layouts.metronic.app')

@section('title', $tag ? 'Edit Tag' : 'New Tag')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core / Tags</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $tag ? 'Edit Tag' : 'New Tag' }}</h1>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.tags.index') }}">Back to Tags</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form
                action="{{ $tag ? route('cms-core.tags.update', $tag) : route('cms-core.tags.store') }}"
                class="kt-form"
                method="POST"
            >
                @csrf
                @if($tag) @method('PUT') @endif

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="name" required type="text" value="{{ old('name', $tag?->name) }}">
                        </div>
                        @error('name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Slug</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="slug" placeholder="auto-generated" type="text" value="{{ old('slug', $tag?->slug) }}">
                        </div>
                        @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea class="kt-textarea min-h-[80px]" name="description">{{ old('description', $tag?->description) }}</textarea>
                        </div>
                        @error('description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.tags.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">{{ $tag ? 'Save Changes' : 'Create Tag' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
