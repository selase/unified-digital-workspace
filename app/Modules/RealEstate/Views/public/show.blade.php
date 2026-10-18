@extends('layouts.metronic.app')

@section('title', $listing->title)

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    <div>
        <a href="{{ route('real-estate.public.catalog') }}" class="text-xs text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
            &larr; Back to Listings Catalog
        </a>
    </div>

    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Showcase Image --}}
            @if($listing->image_path)
                <div class="rounded-xl overflow-hidden border border-border shadow-sm max-h-96">
                    <img src="{{ Storage::url($listing->image_path) }}" alt="{{ $listing->title }}" class="w-full h-full object-cover">
                </div>
            @endif

            <div class="kt-card p-6 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="kt-badge kt-badge-primary font-mono text-xs">{{ $listing->size_dimension }}</span>
                    <span class="kt-badge kt-badge-outline uppercase text-xs">{{ $listing->tenure_type }}</span>
                    <span class="kt-badge kt-badge-success text-xs">{{ str_replace('_', ' ', $listing->compliance_stage) }}</span>
                </div>

                <h1 class="text-2xl font-bold text-foreground">{{ $listing->title }}</h1>
                <div class="text-sm text-muted-foreground flex items-center gap-1">
                    <i class="ki-filled ki-geolocation text-sm"></i>
                    {{ $listing->location }}
                </div>

                <div class="text-2xl font-extrabold text-foreground pt-2">
                    {{ $listing->formattedPrice() }}
                </div>

                <div class="prose dark:prose-invert text-sm text-foreground/90 border-t border-border pt-4">
                    <p>{{ $listing->description }}</p>
                </div>
            </div>
        </div>

        <div>
            <div class="kt-card p-5 space-y-4">
                <h3 class="text-base font-bold text-foreground">Inquire About This Property</h3>
                <p class="text-xs text-muted-foreground">
                    Submit your interest. Our real estate advisory team will arrange a physical site inspection.
                </p>

                <form action="{{ route('real-estate.public.inquire', $listing) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Full Name *</label>
                        <input type="text" name="full_name" class="kt-input" placeholder="e.g. Samuel Mensah" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Email Address *</label>
                        <input type="email" name="email" class="kt-input" placeholder="samuel@client.com" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Phone Number *</label>
                        <input type="text" name="phone" class="kt-input" placeholder="+233244001122" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Message / Inspection Request</label>
                        <textarea name="message" class="kt-input" rows="3" placeholder="I would like to schedule a site visit this Saturday..."></textarea>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">Submit Property Inquiry</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
