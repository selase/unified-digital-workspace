@extends('layouts.metronic.app')

@section('title', 'Client Real Estate & Plot Portal')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Client Real Estate &amp; Land Portal</h1>
            <p class="text-sm text-muted-foreground">
                Track compliance stage, inspect cadastral documentation, manage installment payments, and order building artisans &amp; materials.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
                &larr; Real Estate Desk
            </a>
            <a href="{{ route('real-estate.public.catalog') }}" target="_blank" class="kt-btn kt-btn-primary kt-btn-sm">
                Browse More Plots
            </a>
        </div>
    </div>

    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Search & Filter Bar for Allocations --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-card p-4 rounded-xl border border-border">
        <form action="{{ route('real-estate.portal.index') }}" method="GET" class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by allocation # or plot..." class="kt-input kt-input-sm text-xs w-56">
            <select name="status" class="kt-select kt-select-sm text-xs w-36">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
            </select>
            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Filter</button>
            @if(request()->anyFilled(['search', 'status']))
                <a href="{{ route('real-estate.portal.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
            @endif
        </form>
        <div class="text-xs text-muted-foreground">
            Showing <span class="font-semibold text-foreground">{{ $allocations->total() }}</span> allocated parcel(s)
        </div>
    </div>

    {{-- Allocated Plots Cards --}}
    @forelse($allocations as $alloc)
        @php
            $parcel = $alloc->parcel;
            $step = $parcel ? $parcel->stageStepNumber() : 1;
        @endphp
        <div class="kt-card overflow-hidden">
            <div class="kt-card-header bg-muted/30">
                <div class="flex items-center gap-3">
                    <span class="kt-badge kt-badge-primary font-mono text-xs">{{ $alloc->allocation_number }}</span>
                    <h3 class="kt-card-title text-base font-bold text-foreground">
                        Plot {{ $parcel?->plot_number }} &bull; {{ $parcel?->project?->name }}
                    </h3>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Spatial Overlap Badge --}}
                    @if($parcel)
                        @php
                            $overlapConflict = $parcel->checkSpatialOverlap();
                        @endphp
                        @if($overlapConflict)
                            <span class="kt-badge kt-badge-xs kt-badge-danger">
                                <i class="ki-filled ki-cross-circle text-[10px] mr-1"></i>
                                Spatial Overlap Warning
                            </span>
                        @elseif($parcel->hasSpatialCoordinates())
                            <span class="kt-badge kt-badge-xs kt-badge-success">
                                <i class="ki-filled ki-check-circle text-[10px] mr-1"></i>
                                Coordinates Verified
                            </span>
                        @endif

                        {{-- 30-Day Search Badge --}}
                        @if($parcel->is_legacy)
                            <span class="kt-badge kt-badge-xs kt-badge-secondary">Legacy Title</span>
                        @elseif($parcel->isSearchExpired())
                            <span class="kt-badge kt-badge-xs kt-badge-danger">
                                <i class="ki-filled ki-information text-[10px] mr-1"></i>
                                Search Expired (&gt;30d)
                            </span>
                        @elseif($parcel->search_issue_date)
                            <span class="kt-badge kt-badge-xs kt-badge-success">
                                Search Valid ({{ $parcel->searchDaysRemaining() }}d)
                            </span>
                        @endif
                    @endif

                    <span class="kt-badge kt-badge-outline uppercase">{{ $parcel?->tenure_type }}</span>
                    <span class="kt-badge kt-badge-success uppercase">{{ $alloc->status }}</span>
                </div>
            </div>

            <div class="kt-card-body p-6 space-y-6">
                {{-- 4-Stage Compliance Visual Stepper --}}
                <div>
                    <div class="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">Ghana Land Compliance Lifecycle</div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {{-- Step 1: Due Diligence --}}
                        <div class="p-3 rounded-lg border {{ $step >= 1 ? 'border-primary bg-primary/5' : 'border-border' }}">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span>1. Due Diligence</span>
                                @if($step > 1)
                                    <span class="text-emerald-600 font-bold">&check; Done</span>
                                @elseif($step === 1)
                                    <span class="text-primary font-bold">Active</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-muted-foreground mt-1">Lands Commission search &amp; site coordinate validation.</div>
                        </div>

                        {{-- Step 2: Registration --}}
                        <div class="p-3 rounded-lg border {{ $step >= 2 ? 'border-primary bg-primary/5' : 'border-border' }}">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span>2. Registration</span>
                                @if($step > 2)
                                    <span class="text-emerald-600 font-bold">&check; Done</span>
                                @elseif($step === 2)
                                    <span class="text-primary font-bold">Active</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-muted-foreground mt-1">Stamped Indenture &amp; Land Title Certificate issue.</div>
                        </div>

                        {{-- Step 3: Permitting --}}
                        <div class="p-3 rounded-lg border {{ $step >= 3 ? 'border-primary bg-primary/5' : 'border-border' }}">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span>3. Permitting</span>
                                @if($step > 3)
                                    <span class="text-emerald-600 font-bold">&check; Done</span>
                                @elseif($step === 3)
                                    <span class="text-primary font-bold">Active</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-muted-foreground mt-1">GIA Architect &amp; GhIE Structural Engineer MMDA building permit.</div>
                        </div>

                        {{-- Step 4: Ready to Build --}}
                        <div class="p-3 rounded-lg border {{ $step >= 4 ? 'border-emerald-600 bg-emerald-500/5' : 'border-border' }}">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span>4. Ready to Build</span>
                                @if($step >= 4)
                                    <span class="text-emerald-600 font-bold">&check; Cleared</span>
                                @else
                                    <span class="text-muted-foreground">Pending</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-muted-foreground mt-1">Full statutory clearance; construction unlocked.</div>
                        </div>
                    </div>
                </div>

                {{-- Installment Payment Tracker & Pay Action --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="p-4 rounded-lg bg-muted/20 border border-border space-y-3">
                        <div class="text-xs font-semibold uppercase text-muted-foreground">Payment Summary</div>
                        <div class="space-y-1">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Total Price:</span>
                                <span class="font-bold text-foreground">{{ $alloc->formattedTotalPrice() }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Total Paid:</span>
                                <span class="font-bold text-emerald-600">{{ $alloc->formattedAmountPaid() }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Balance Due:</span>
                                <span class="font-bold text-rose-600">{{ $alloc->formattedOutstandingBalance() }}</span>
                            </div>
                        </div>

                        {{-- Installment Schedule Items & Receipts --}}
                        <div class="pt-3 border-t border-border space-y-1.5">
                            <div class="text-[10px] font-bold uppercase text-muted-foreground">Installment Schedule</div>
                            @foreach($alloc->installments as $inst)
                                <div class="flex items-center justify-between text-xs p-1.5 rounded bg-card border border-border">
                                    <div>
                                        <span class="font-mono font-semibold">#{{ $inst->installment_number }}:</span>
                                        <span>{{ $inst->formattedAmountDue() }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        @if($inst->status === 'paid')
                                            <span class="kt-badge kt-badge-xs kt-badge-success">Paid</span>
                                            <a href="{{ route('real-estate.installments.receipt', $inst) }}" class="text-[10px] text-primary hover:underline font-semibold flex items-center gap-0.5">
                                                <i class="ki-filled ki-printer text-[10px]"></i>
                                                Receipt
                                            </a>
                                        @else
                                            <span class="kt-badge kt-badge-xs kt-badge-warning">{{ $inst->status }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @php
                            $pending = $alloc->installments->firstWhere('status', 'pending');
                        @endphp
                        @if($pending)
                            <div class="pt-2 border-t border-border">
                                <div class="text-xs font-semibold text-foreground mb-2">Next Installment (#{{ $pending->installment_number }})</div>
                                <form action="{{ route('real-estate.installments.pay', $pending) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <div class="flex items-center gap-2">
                                        <input type="number" step="0.01" min="1" name="amount_ghs" value="{{ $pending->amount_due_pesewas / 100 }}" class="kt-input kt-input-sm text-xs" required>
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Pay / Top-Up</button>
                                    </div>
                                    <div class="text-[11px] text-muted-foreground">Due on {{ $pending->due_date?->format('d/m/Y') }}</div>
                                </form>
                            </div>
                        @else
                            <div class="text-xs text-emerald-600 font-bold pt-2">All scheduled installments completed!</div>
                        @endif
                    </div>

                    {{-- Private Document Vault for this Parcel --}}
                    <div class="p-4 rounded-lg bg-muted/20 border border-border space-y-3">
                        <div class="text-xs font-semibold uppercase text-muted-foreground">Private Document Vault</div>
                        <div class="space-y-2">
                            @forelse($parcel?->documents ?? [] as $doc)
                                <div class="flex items-center justify-between text-xs p-2 bg-card rounded border border-border">
                                    <div>
                                        <div class="font-semibold text-foreground">{{ $doc->title }}</div>
                                        <div class="text-[10px] text-muted-foreground font-mono">{{ $doc->document_type }}</div>
                                    </div>
                                    <a href="{{ route('real-estate.documents.download', $doc) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                        <i class="ki-filled ki-file-down text-xs"></i>
                                        Download
                                    </a>
                                </div>
                            @empty
                                <div class="text-xs text-muted-foreground py-2">No documents published yet.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Order Experts, Surveyors & Materials --}}
                    <div class="p-4 rounded-lg bg-muted/20 border border-border space-y-3">
                        <div class="text-xs font-semibold uppercase text-muted-foreground">Request Surveyors, Artisans &amp; Materials</div>
                        <p class="text-xs text-muted-foreground">
                            Order building sand, cement blocks, geomatic surveyors, or architects with transparent 3-way escrow protection.
                        </p>
                        @if($parcel)
                            <form action="{{ route('real-estate.parcels.order-vendor', $parcel) }}" method="POST" class="space-y-2">
                                @csrf
                                <select name="category" class="kt-select kt-select-sm text-xs" required>
                                    <option value="surveying">Cadastral Boundary Surveyor</option>
                                    <option value="architecture_engineering">Architect &amp; Structural Engineer</option>
                                    <option value="building_materials">Building Materials (Sand, Cement, Blocks)</option>
                                    <option value="land_registration">Land Registration Attorney</option>
                                </select>
                                <input type="text" name="title" class="kt-input kt-input-sm text-xs" placeholder="e.g. 10 Trips Fine River Sand" required>
                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline w-full justify-center">Order via Vendor Marketplace</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="kt-card p-12 text-center text-muted-foreground">
            <i class="ki-filled ki-cube-3 text-4xl mb-2 text-muted-foreground/50"></i>
            <div class="text-base font-semibold text-foreground">No plot allocations found for your account.</div>
            <p class="text-xs mt-1">Please contact the estate sales office or browse our public property listings.</p>
        </div>
    @endforelse

    @if($allocations->hasPages())
        <div class="p-4 bg-card rounded-xl border border-border">
            {{ $allocations->links() }}
        </div>
    @endif
</div>
@endsection
