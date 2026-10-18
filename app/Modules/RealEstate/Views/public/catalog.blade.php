@extends('layouts.metronic.app')

@section('title', 'Featured Real Estate & Land Listings')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Featured Land &amp; Property Listings</h1>
            <p class="text-sm text-muted-foreground">
                Verified plots with clean title, cadastral boundary verification, and MMDA building permit readiness.
            </p>
        </div>
        <div>
            <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
                Real Estate Office &rarr;
            </a>
        </div>
    </div>

    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Search & Filter Bar --}}
    <div class="bg-card p-4 rounded-xl border border-border">
        <form action="{{ route('real-estate.public.catalog') }}" method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by title, location or keywords..." class="kt-input kt-input-sm text-xs w-full">
            </div>
            <div>
                <select name="tenure_type" class="kt-select kt-select-sm text-xs">
                    <option value="">All Tenures</option>
                    <option value="PRIVATE" @selected(request('tenure_type') === 'PRIVATE')>Private / Freehold</option>
                    <option value="STOOL" @selected(request('tenure_type') === 'STOOL')>Stool Land</option>
                    <option value="FAMILY" @selected(request('tenure_type') === 'FAMILY')>Family Land</option>
                    <option value="STATE" @selected(request('tenure_type') === 'STATE')>State Land</option>
                </select>
            </div>
            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Search Listings</button>
            @if(request()->anyFilled(['search', 'tenure_type']))
                <a href="{{ route('real-estate.public.catalog') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
            @endif
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($listings as $listing)
            <div class="kt-card overflow-hidden flex flex-col justify-between group">
                {{-- Listing Image --}}
                <div class="relative h-48 w-full bg-muted overflow-hidden">
                    @if($listing->image_path)
                        <img src="{{ Storage::url($listing->image_path) }}" alt="{{ $listing->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    @else
                        <div class="w-full h-full flex flex-col items-center justify-center text-muted-foreground bg-muted/40">
                            <i class="ki-filled ki-picture text-3xl mb-1 text-muted-foreground/40"></i>
                            <span class="text-xs font-medium">Estate Plot Showcase</span>
                        </div>
                    @endif
                    @if($listing->is_featured)
                        <div class="absolute top-3 left-3">
                            <span class="kt-badge kt-badge-warning kt-badge-sm shadow font-semibold">Featured</span>
                        </div>
                    @endif
                </div>

                <div class="p-5 space-y-3 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="kt-badge kt-badge-primary text-xs font-mono">{{ $listing->size_dimension }}</span>
                            <span class="kt-badge kt-badge-outline uppercase text-[10px]">{{ $listing->tenure_type }}</span>
                        </div>

                        <h3 class="text-lg font-bold text-foreground">
                            <a href="{{ route('real-estate.public.show', $listing) }}" class="hover:text-primary transition-colors">
                                {{ $listing->title }}
                            </a>
                        </h3>

                        <div class="text-xs text-muted-foreground flex items-center gap-1 mt-1">
                            <i class="ki-filled ki-geolocation text-xs"></i>
                            {{ $listing->location }}
                        </div>

                        <div class="text-xs text-muted-foreground line-clamp-2 mt-2">
                            {{ $listing->description }}
                        </div>
                    </div>

                    <div class="p-3 bg-muted/30 rounded-lg flex items-center justify-between mt-4">
                        <div>
                            <div class="text-[10px] uppercase font-semibold text-muted-foreground">Price</div>
                            <div class="text-base font-bold text-foreground">{{ $listing->formattedPrice() }}</div>
                        </div>
                        <div class="text-end">
                            <div class="text-[10px] uppercase font-semibold text-muted-foreground">Compliance Stage</div>
                            <span class="kt-badge kt-badge-xs kt-badge-success">{{ str_replace('_', ' ', $listing->compliance_stage) }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-5 pt-0">
                    <a href="{{ route('real-estate.public.show', $listing) }}" class="kt-btn kt-btn-primary w-full justify-center">
                        View Details &amp; Inquire
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-3 kt-card p-12 text-center text-muted-foreground">
                <div class="text-base font-semibold text-foreground">No property listings found matching your search.</div>
                <p class="text-xs mt-1">Please try different keywords or check back soon.</p>
            </div>
        @endforelse
    </div>

    @if($listings->hasPages())
        <div class="p-4 bg-card rounded-xl border border-border">
            {{ $listings->links() }}
        </div>
    @endif
</div>
@endsection
