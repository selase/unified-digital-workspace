@extends('layouts.metronic.app')

@section('title', $category ? 'Edit Category' : 'New Category')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core / Categories</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $category ? 'Edit Category' : 'New Category' }}</h1>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.categories.index') }}">Back to Categories</a>
            </div>
        </div>

        <div class="kt-card p-6">
            <form
                action="{{ $category ? route('cms-core.categories.update', $category) : route('cms-core.categories.store') }}"
                class="kt-form"
                method="POST"
            >
                @csrf
                @if($category) @method('PUT') @endif

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="name" required type="text" value="{{ old('name', $category?->name) }}">
                        </div>
                        @error('name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Slug</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="slug" placeholder="auto-generated" type="text" value="{{ old('slug', $category?->slug) }}">
                        </div>
                        @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea class="kt-textarea min-h-[80px]" name="description">{{ old('description', $category?->description) }}</textarea>
                        </div>
                        @error('description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Parent Category</label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="parent_id">
                                <option value="">None (top level)</option>
                                @foreach($parentCategories as $parent)
                                    <option value="{{ $parent->id }}" @selected((int) old('parent_id', $category?->parent_id) === $parent->id)>
                                        {{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Sort Order</label>
                        <div class="kt-form-control">
                            <input class="kt-input" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label flex items-center gap-2">
                            <input class="kt-checkbox" name="is_active" type="checkbox" value="1" @checked(old('is_active', $category?->is_active ?? true))>
                            Active
                        </label>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end gap-3">
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.categories.index') }}">Cancel</a>
                    <button class="kt-btn kt-btn-primary" type="submit">{{ $category ? 'Save Changes' : 'Create Category' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
