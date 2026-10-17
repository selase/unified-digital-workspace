@extends('layouts.metronic.app')

@section('title', $category ? 'Edit Category' : 'New Category')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="{{ $category ? 'Edit Category: ' . $category->name : 'New Category' }}"
        description="Configure taxonomy classification, parent associations, and circulation visibility."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.categories.index') }}">
                Back to Categories
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

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
                    <label class="kt-form-label font-medium">Name <span class="text-destructive">*</span></label>
                    <div class="kt-form-control mt-1.5">
                        <input class="kt-input w-full" name="name" required type="text" value="{{ old('name', $category?->name) }}" placeholder="e.g. Clinical News, Policies">
                    </div>
                    @error('name') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label font-medium">Slug</label>
                    <div class="kt-form-control mt-1.5">
                        <input class="kt-input w-full" name="slug" placeholder="auto-generated-if-empty" type="text" value="{{ old('slug', $category?->slug) }}">
                    </div>
                    @error('slug') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="kt-form-item lg:col-span-2">
                    <label class="kt-form-label font-medium">Description</label>
                    <div class="kt-form-control mt-1.5">
                        <textarea class="kt-textarea w-full min-h-[90px]" name="description" placeholder="Brief explanation of this content category">{{ old('description', $category?->description) }}</textarea>
                    </div>
                    @error('description') <p class="text-xs text-destructive mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label font-medium">Parent Category</label>
                    <div class="kt-form-control mt-1.5">
                        <select class="kt-select w-full" name="parent_id">
                            <option value="">None (top level category)</option>
                            @foreach($parentCategories as $parent)
                                <option value="{{ $parent->id }}" @selected((int) old('parent_id', $category?->parent_id) === $parent->id)>
                                    {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="kt-form-item">
                    <label class="kt-form-label font-medium">Sort Order</label>
                    <div class="kt-form-control mt-1.5">
                        <input class="kt-input w-full" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category?->sort_order ?? 0) }}">
                    </div>
                </div>

                <div class="kt-form-item lg:col-span-2">
                    <label class="kt-form-label flex items-center gap-2 cursor-pointer">
                        <input class="kt-checkbox" name="is_active" type="checkbox" value="1" @checked(old('is_active', $category?->is_active ?? true))>
                        <span class="text-sm font-medium text-foreground">Active Category (visible in post classification and navigation)</span>
                    </label>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 pt-4 border-t border-border">
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.categories.index') }}">Cancel</a>
                <button class="kt-btn kt-btn-primary" type="submit">{{ $category ? 'Save Changes' : 'Create Category' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
