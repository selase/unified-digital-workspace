@extends('layouts.metronic.app')

@section('title', 'Edit Property Listing: ' . $listing->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Edit Property Listing</h1>
            <p class="text-sm text-muted-foreground">Update public marketing catalog listing, price, and showcase photography.</p>
        </div>
        <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
            &larr; Back to Real Estate Hub
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-sm space-y-1">
            <div class="font-semibold">Validation Errors:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title text-base font-semibold">Listing: {{ $listing->title }}</h3>
        </div>
        <div class="kt-card-body p-6">
            <form action="{{ route('real-estate.listings.update', $listing) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Listing Title *</label>
                        <input type="text" name="title" class="kt-input" value="{{ old('title', $listing->title) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Location / Area *</label>
                        <input type="text" name="location" class="kt-input" value="{{ old('location', $listing->location) }}" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Price (GHS) *</label>
                        <input type="number" step="0.01" min="1" name="price_ghs" class="kt-input" value="{{ old('price_ghs', $listing->price_pesewas / 100) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Size Dimension *</label>
                        <input type="text" name="size_dimension" class="kt-input" value="{{ old('size_dimension', $listing->size_dimension) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Tenure Classification *</label>
                        <select name="tenure_type" class="kt-select" required>
                            <option value="PRIVATE" @selected(old('tenure_type', $listing->tenure_type) === 'PRIVATE')>Private / Freehold</option>
                            <option value="STOOL" @selected(old('tenure_type', $listing->tenure_type) === 'STOOL')>Stool / Skin Land</option>
                            <option value="FAMILY" @selected(old('tenure_type', $listing->tenure_type) === 'FAMILY')>Family Land</option>
                            <option value="STATE" @selected(old('tenure_type', $listing->tenure_type) === 'STATE')>State / Lands Commission</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Compliance Stage *</label>
                        <select name="compliance_stage" class="kt-select" required>
                            <option value="IN_DUE_DILIGENCE" @selected(old('compliance_stage', $listing->compliance_stage) === 'IN_DUE_DILIGENCE')>1. In Due Diligence</option>
                            <option value="PURCHASE_AND_REGISTRATION" @selected(old('compliance_stage', $listing->compliance_stage) === 'PURCHASE_AND_REGISTRATION')>2. Purchase &amp; Registration</option>
                            <option value="PERMITTING_AND_PRE_CONSTRUCTION" @selected(old('compliance_stage', $listing->compliance_stage) === 'PERMITTING_AND_PRE_CONSTRUCTION')>3. Permitting &amp; Pre-Construction</option>
                            <option value="PROJECT_READY_FOR_BUILD" @selected(old('compliance_stage', $listing->compliance_stage) === 'PROJECT_READY_FOR_BUILD')>4. Project Ready to Build</option>
                        </select>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Linked Land Parcel (Optional)</label>
                        <select name="land_parcel_id" class="kt-select">
                            <option value="">None (Independent Listing)</option>
                            @foreach($parcels as $parcel)
                                <option value="{{ $parcel->id }}" @selected(old('land_parcel_id', $listing->land_parcel_id) == $parcel->id)>
                                    Plot {{ $parcel->plot_number }} ({{ $parcel->project?->name ?? 'Enclave' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="kt-form-label text-xs">Description</label>
                    <textarea name="description" class="kt-input" rows="4">{{ old('description', $listing->description) }}</textarea>
                </div>

                {{-- Listing Image Upload & Current Image Preview --}}
                <div class="p-4 bg-muted/20 border border-border rounded-lg space-y-3">
                    <div class="text-xs font-semibold text-foreground">Property Photography / Showcase Image</div>
                    @if($listing->image_path)
                        <div class="flex items-center gap-4">
                            <img src="{{ Storage::url($listing->image_path) }}" alt="{{ $listing->title }}" class="h-24 w-36 object-cover rounded-lg border border-border shadow-sm">
                            <div class="text-xs text-muted-foreground">
                                <div class="font-semibold text-foreground">Current Image Uploaded</div>
                                <div class="text-[11px] font-mono mt-0.5 truncate max-w-xs">{{ $listing->image_path }}</div>
                                <div class="text-[10px] mt-1 text-muted-foreground">Upload a new file below to replace this image.</div>
                            </div>
                        </div>
                    @endif
                    <div>
                        <label class="kt-form-label text-xs">Upload New Image (JPEG, PNG, WebP &bull; Max 5MB)</label>
                        <input type="file" name="image" class="kt-input text-xs" accept="image/*">
                    </div>
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="kt-checkbox" @checked(old('is_featured', $listing->is_featured))>
                        <span class="text-xs text-foreground font-medium">Featured Listing (Banner priority)</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_published" value="1" class="kt-checkbox" @checked(old('is_published', $listing->is_published))>
                        <span class="text-xs text-foreground font-medium">Published on Public Catalog</span>
                    </label>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-border">
                    <button type="submit" class="kt-btn kt-btn-primary">Update Listing</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Danger Zone: Soft Delete --}}
    <div class="kt-card border-rose-500/20 bg-rose-500/5">
        <div class="kt-card-body p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h4 class="text-sm font-bold text-rose-700 dark:text-rose-400">Soft-Delete Listing</h4>
                <p class="text-xs text-muted-foreground mt-0.5">Remove this listing from the public catalog. Historical inquiries and lead links remain intact.</p>
            </div>
            <form action="{{ route('real-estate.listings.destroy', $listing) }}" method="POST" onsubmit="return confirm('Are you sure you want to soft-delete listing {{ $listing->title }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="kt-btn kt-btn-danger kt-btn-sm">
                    <i class="ki-filled ki-trash text-xs"></i>
                    Soft-Delete Listing
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

