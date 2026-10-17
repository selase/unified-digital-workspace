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

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($listings as $listing)
            <div class="kt-card overflow-hidden flex flex-col justify-between">
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="kt-badge kt-badge-primary text-xs font-mono">{{ $listing->size_dimension }}</span>
                        <span class="kt-badge kt-badge-outline uppercase text-[10px]">{{ $listing->tenure_type }}</span>
                    </div>

                    <h3 class="text-lg font-bold text-foreground">
                        <a href="{{ route('real-estate.public.show', $listing) }}" class="hover:text-primary transition-colors">
                            {{ $listing->title }}
                        </a>
                    </h3>

                    <div class="text-xs text-muted-foreground flex items-center gap-1">
                        <i class="ki-filled ki-geolocation text-xs"></i>
                        {{ $listing->location }}
                    </div>

                    <div class="text-xs text-muted-foreground line-clamp-3">
                        {{ $listing->description }}
                    </div>

                    <div class="p-3 bg-muted/30 rounded-lg flex items-center justify-between">
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
                <div class="text-base font-semibold text-foreground">No property listings published currently.</div>
                <p class="text-xs mt-1">Please check back soon or inquire at our office.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

