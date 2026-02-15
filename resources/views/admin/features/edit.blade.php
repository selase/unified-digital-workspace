@extends('layouts.metronic.app')

@section('title', 'Edit Feature')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Feature Registry</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Edit Feature</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Update entitlement metadata for {{ $feature->name }}.</p>
                </div>
                <a href="{{ route('features.index') }}" class="kt-btn kt-btn-outline">Back to Features</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <form id="editFeatureForm" class="kt-form" action="{{ route('features.update', $feature->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="kt-form-item">
                        <label class="kt-form-label">Feature Name <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="name" class="kt-input" placeholder="e.g. AI Content Generation"
                                value="{{ old('name', $feature->name) }}" required />
                        </div>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Slug <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <input type="text" name="slug" class="kt-input" placeholder="e.g. ai-content-generation"
                                value="{{ old('slug', $feature->slug) }}" required />
                        </div>
                        <p class="kt-form-description">Unique identifier for this feature in code.</p>
                    </div>

                    <div class="kt-form-item">
                        <label class="kt-form-label">Type <span class="text-destructive">*</span></label>
                        <div class="kt-form-control">
                            <select class="kt-select" name="type" required>
                                <option value="boolean" {{ old('type', $feature->type) === 'boolean' ? 'selected' : '' }}>Boolean (Yes/No)</option>
                                <option value="limit" {{ old('type', $feature->type) === 'limit' ? 'selected' : '' }}>Limit (Count/Quota)</option>
                                <option value="metered" {{ old('type', $feature->type) === 'metered' ? 'selected' : '' }}>Metered (Usage Tracking)</option>
                            </select>
                        </div>
                        <p class="kt-form-description">
                            <strong>Boolean:</strong> Enabled or Disabled.
                            <br>
                            <strong>Limit:</strong> A numeric limit max (e.g. 10 Users).
                            <br>
                            <strong>Metered:</strong> Pay-as-you-go usage.
                        </p>
                    </div>

                    <div class="kt-form-item lg:col-span-2">
                        <label class="kt-form-label">Description</label>
                        <div class="kt-form-control">
                            <textarea name="description" class="kt-textarea" rows="3">{{ old('description', $feature->description) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="kt-form-item mt-6">
                    <label class="kt-form-label">Associated Permissions</label>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 mt-4">
                        @foreach($permissions->groupBy('category') as $category => $perms)
                            <div class="rounded-lg border border-border bg-muted/30 p-4">
                                <h3 class="text-sm font-semibold text-foreground mb-3">{{ ucfirst($category) }}</h3>
                                <div class="space-y-2">
                                    @foreach($perms as $permission)
                                        <label class="flex items-center gap-2 text-sm text-foreground">
                                            <input class="kt-checkbox" type="checkbox" name="permissions[]"
                                                value="{{ $permission->id }}" id="perm_{{ $permission->id }}"
                                                {{ in_array($permission->id, old('permissions', $featurePermissions)) ? 'checked' : '' }} />
                                            <span>{{ $permission->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="kt-form-description mt-3">Selecting permissions here will lock them behind this feature entitlement.</p>
                </div>

                <div class="kt-form-actions mt-8 flex items-center justify-end gap-3">
                    <a href="{{ route('features.index') }}" class="kt-btn kt-btn-outline">Discard</a>
                    <button type="submit" class="kt-btn kt-btn-primary">
                        <span class="indicator-label">Update Feature</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
