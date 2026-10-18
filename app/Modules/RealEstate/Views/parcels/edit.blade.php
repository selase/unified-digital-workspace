@extends('layouts.metronic.app')

@section('title', 'Edit Plot: ' . $parcel->plot_number)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Edit Land Parcel / Plot</h1>
            <p class="text-sm text-muted-foreground">Modify plot geometry, pricing, tenure or boundary coordinates.</p>
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

    {{-- Compliance & Geometry Status Badges Banner --}}
    <div class="p-4 rounded-xl bg-card border border-border shadow-sm flex flex-wrap items-center gap-4">
        <div>
            <div class="text-[10px] font-semibold uppercase text-muted-foreground">Compliance Stage</div>
            <div class="mt-0.5">
                <span class="kt-badge kt-badge-sm kt-badge-primary">{{ str_replace('_', ' ', $parcel->current_stage) }}</span>
            </div>
        </div>

        <div>
            <div class="text-[10px] font-semibold uppercase text-muted-foreground">Spatial Overlap Check</div>
            <div class="mt-0.5">
                @php
                    $overlapConflict = $parcel->checkSpatialOverlap();
                @endphp
                @if($overlapConflict)
                    <span class="kt-badge kt-badge-sm kt-badge-danger">
                        <i class="ki-filled ki-cross-circle text-xs mr-1"></i>
                        Overlap Conflict (Plot {{ $overlapConflict->plot_number }})
                    </span>
                @elseif($parcel->hasSpatialCoordinates())
                    <span class="kt-badge kt-badge-sm kt-badge-success">
                        <i class="ki-filled ki-check-circle text-xs mr-1"></i>
                        No Overlap Detected
                    </span>
                @else
                    <span class="kt-badge kt-badge-sm kt-badge-outline">No Coordinates Set</span>
                @endif
            </div>
        </div>

        <div>
            <div class="text-[10px] font-semibold uppercase text-muted-foreground">30-Day Search Rule</div>
            <div class="mt-0.5">
                @if($parcel->is_legacy)
                    <span class="kt-badge kt-badge-sm kt-badge-secondary">Legacy (Exempt)</span>
                @elseif(! $parcel->search_issue_date)
                    <span class="kt-badge kt-badge-sm kt-badge-warning">Search Pending</span>
                @elseif($parcel->isSearchExpired())
                    <span class="kt-badge kt-badge-sm kt-badge-danger">
                        <i class="ki-filled ki-information text-xs mr-1"></i>
                        Search Expired (&gt;30 Days)
                    </span>
                @else
                    <span class="kt-badge kt-badge-sm kt-badge-success">
                        <i class="ki-filled ki-check text-xs mr-1"></i>
                        Search Valid ({{ $parcel->searchDaysRemaining() }}d left)
                    </span>
                @endif
            </div>
        </div>
    </div>

    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title text-base font-semibold">Plot Information: {{ $parcel->plot_number }}</h3>
        </div>
        <div class="kt-card-body p-6">
            <form action="{{ route('real-estate.parcels.update', $parcel) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Project Enclave *</label>
                        <select name="real_estate_project_id" class="kt-select" required>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" @selected(old('real_estate_project_id', $parcel->real_estate_project_id) == $p->id)>
                                    {{ $p->name }} ({{ $p->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Plot # &amp; Block *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" name="plot_number" class="kt-input font-mono" value="{{ old('plot_number', $parcel->plot_number) }}" required>
                            <input type="text" name="block_number" class="kt-input font-mono" value="{{ old('block_number', $parcel->block_number) }}" placeholder="Block">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Dimension *</label>
                        <input type="text" name="size_dimension" class="kt-input" value="{{ old('size_dimension', $parcel->size_dimension) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Acreage *</label>
                        <input type="number" step="0.0001" min="0.01" name="acreage" class="kt-input" value="{{ old('acreage', $parcel->acreage) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Price (GHS) *</label>
                        <input type="number" step="0.01" min="1" name="price_ghs" class="kt-input" value="{{ old('price_ghs', $parcel->price_pesewas / 100) }}" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Tenure Classification *</label>
                        <select name="tenure_type" class="kt-select" required>
                            <option value="PRIVATE" @selected(old('tenure_type', $parcel->tenure_type) === 'PRIVATE')>Private / Freehold</option>
                            <option value="STOOL" @selected(old('tenure_type', $parcel->tenure_type) === 'STOOL')>Stool / Skin Land</option>
                            <option value="FAMILY" @selected(old('tenure_type', $parcel->tenure_type) === 'FAMILY')>Family Land</option>
                            <option value="STATE" @selected(old('tenure_type', $parcel->tenure_type) === 'STATE')>State / Lands Commission</option>
                        </select>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Commercial Status *</label>
                        <select name="status" class="kt-select" required>
                            <option value="available" @selected(old('status', $parcel->status) === 'available')>Available</option>
                            <option value="reserved" @selected(old('status', $parcel->status) === 'reserved')>Reserved</option>
                            <option value="allocated" @selected(old('status', $parcel->status) === 'allocated')>Allocated</option>
                            <option value="sold" @selected(old('status', $parcel->status) === 'sold')>Sold Out</option>
                        </select>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_legacy" value="1" class="kt-checkbox" @checked(old('is_legacy', $parcel->is_legacy))>
                        <span class="text-xs text-foreground font-medium">Historical / Legacy Plot (Bypass 30-day search re-validation)</span>
                    </label>
                </div>

                <div class="p-4 bg-muted/20 border border-border rounded-lg space-y-2">
                    <div class="text-xs font-semibold text-foreground">Spatial Bounding Coordinates (Degrees Decimal)</div>
                    <div class="text-[11px] text-muted-foreground">Used for real-time spatial overlap detection against neighboring plots in the same enclave.</div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-1">
                        <div>
                            <label class="text-[10px] text-muted-foreground">Min Lat</label>
                            <input type="number" step="0.0000001" name="min_lat" class="kt-input text-xs" value="{{ old('min_lat', $parcel->min_lat) }}">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted-foreground">Max Lat</label>
                            <input type="number" step="0.0000001" name="max_lat" class="kt-input text-xs" value="{{ old('max_lat', $parcel->max_lat) }}">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted-foreground">Min Lng</label>
                            <input type="number" step="0.0000001" name="min_lng" class="kt-input text-xs" value="{{ old('min_lng', $parcel->min_lng) }}">
                        </div>
                        <div>
                            <label class="text-[10px] text-muted-foreground">Max Lng</label>
                            <input type="number" step="0.0000001" name="max_lng" class="kt-input text-xs" value="{{ old('max_lng', $parcel->max_lng) }}">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-border">
                    <button type="submit" class="kt-btn kt-btn-primary">Update Plot</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Danger Zone: Soft Delete --}}
    <div class="kt-card border-rose-500/20 bg-rose-500/5">
        <div class="kt-card-body p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h4 class="text-sm font-bold text-rose-700 dark:text-rose-400">Soft-Delete Land Parcel</h4>
                <p class="text-xs text-muted-foreground mt-0.5">Archive this parcel. Historical records, documents and allocations are safely preserved.</p>
            </div>
            <form action="{{ route('real-estate.parcels.destroy', $parcel) }}" method="POST" onsubmit="return confirm('Are you sure you want to soft-delete Plot {{ $parcel->plot_number }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="kt-btn kt-btn-danger kt-btn-sm">
                    <i class="ki-filled ki-trash text-xs"></i>
                    Soft-Delete Plot
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

