@extends('layouts.metronic.app')

@section('title', 'Real Estate & Land Compliance Hub')

@section('content')
<div class="space-y-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Real Estate &amp; Land Compliance Hub</h1>
            <p class="text-sm text-muted-foreground">
                Ghana Land Acquisition Lifecycle (4 Stages), Spatial Overlap Protection, Private Document Vault &amp; Client Allocations
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('real-estate.portal.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
                <i class="ki-filled ki-user text-xs"></i>
                Client Portal Preview
            </a>
            <a href="{{ route('real-estate.public.catalog') }}" target="_blank" class="kt-btn kt-btn-primary kt-btn-sm">
                <i class="ki-filled ki-eye text-xs"></i>
                Public Listings Catalog
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-sm space-y-1">
            <div class="font-semibold flex items-center gap-2">
                <i class="ki-filled ki-information-2 text-base"></i>
                Compliance / Validation Error:
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Metrics KPI Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Projects</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $metrics['total_projects'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Enclaves</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Total Plots</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $metrics['total_parcels'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Tracked parcels</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Due Diligence</div>
            <div class="text-xl font-bold text-amber-600 mt-1">{{ $metrics['parcels_in_due_diligence'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Stage 1 searches</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Ready to Build</div>
            <div class="text-xl font-bold text-emerald-600 mt-1">{{ $metrics['parcels_ready_to_build'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Stage 4 approved</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Boundary Risks</div>
            <div class="text-xl font-bold text-rose-600 mt-1">{{ $metrics['unsecured_boundary_warnings'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Missing pillars/walls</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Allocated Value</div>
            <div class="text-base font-bold text-foreground mt-1 truncate">GHS {{ $metrics['total_allocated_ghs'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Contract receivables</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Collected Cash</div>
            <div class="text-base font-bold text-emerald-600 mt-1 truncate">GHS {{ $metrics['total_collected_ghs'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Installment total</div>
        </div>
    </div>

    {{-- Creation Cards: 1. Project, 2. Parcel, 3. Listing --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Register Project --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Create Enclave / Project</h3>
            </div>
            <div class="kt-card-body p-4">
                <form action="{{ route('real-estate.projects.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Name *</label>
                            <input type="text" name="name" class="kt-input text-xs" placeholder="East Legon Hills" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Code *</label>
                            <input type="text" name="code" class="kt-input font-mono text-xs" placeholder="ELH-PH1" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Location *</label>
                            <input type="text" name="location" class="kt-input text-xs" placeholder="Adenta, Accra" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Region *</label>
                            <input type="text" name="region" class="kt-input text-xs" value="Greater Accra" required>
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Total Acreage *</label>
                        <input type="number" step="0.01" min="0" name="total_acreage" class="kt-input text-xs" placeholder="25.50" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Description</label>
                        <textarea name="description" class="kt-input text-xs" rows="2" placeholder="Master planned gated enclave..."></textarea>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline kt-btn-sm w-full justify-center">Create Project</button>
                </form>
            </div>
        </div>

        {{-- Register Land Parcel --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Register Land Parcel with Bounds</h3>
            </div>
            <div class="kt-card-body p-4">
                <form action="{{ route('real-estate.parcels.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Project *</label>
                            <select name="real_estate_project_id" class="kt-select text-xs" required>
                                <option value="">Select</option>
                                @foreach($allProjects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Plot # &amp; Block *</label>
                            <div class="grid grid-cols-2 gap-1">
                                <input type="text" name="plot_number" class="kt-input font-mono text-xs" placeholder="PLT-101" required>
                                <input type="text" name="block_number" class="kt-input font-mono text-xs" placeholder="BLK-A">
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Size *</label>
                            <input type="text" name="size_dimension" class="kt-input text-xs" value="70x100 ft" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Acreage *</label>
                            <input type="number" step="0.0001" min="0.01" name="acreage" class="kt-input text-xs" value="0.1600" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Price GHS *</label>
                            <input type="number" step="0.01" min="1" name="price_ghs" class="kt-input text-xs" placeholder="120000" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Tenure *</label>
                            <select name="tenure_type" class="kt-select text-xs" required>
                                <option value="PRIVATE">Private / Freehold</option>
                                <option value="STOOL">Stool / Skin</option>
                                <option value="FAMILY">Family Land</option>
                                <option value="STATE">State Land</option>
                            </select>
                        </div>
                        <div class="flex items-center pt-4">
                            <label class="inline-flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" name="is_legacy" value="1" class="kt-checkbox">
                                <span class="text-[11px] text-foreground font-medium">Legacy Plot</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-[11px] text-muted-foreground">Spatial Bounds (Min/Max Lat &amp; Lng)</label>
                        <div class="grid grid-cols-4 gap-1">
                            <input type="number" step="0.0000001" name="min_lat" class="kt-input text-[11px] p-1" placeholder="MinLat">
                            <input type="number" step="0.0000001" name="max_lat" class="kt-input text-[11px] p-1" placeholder="MaxLat">
                            <input type="number" step="0.0000001" name="min_lng" class="kt-input text-[11px] p-1" placeholder="MinLng">
                            <input type="number" step="0.0000001" name="max_lng" class="kt-input text-[11px] p-1" placeholder="MaxLng">
                        </div>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary kt-btn-sm w-full justify-center">Register Plot</button>
                </form>
            </div>
        </div>

        {{-- Publish Property Listing --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">Publish Public Property Listing</h3>
            </div>
            <div class="kt-card-body p-4">
                <form action="{{ route('real-estate.listings.store') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Title *</label>
                        <input type="text" name="title" class="kt-input text-xs" placeholder="Serviced 70x100 Plot with Title" required>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Location *</label>
                            <input type="text" name="location" class="kt-input text-xs" placeholder="East Legon Hills" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Price (GHS) *</label>
                            <input type="number" step="0.01" min="1" name="price_ghs" class="kt-input text-xs" placeholder="150000" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Dimension *</label>
                            <input type="text" name="size_dimension" class="kt-input text-xs" value="70x100 ft" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Tenure *</label>
                            <select name="tenure_type" class="kt-select text-xs" required>
                                <option value="PRIVATE">Private / Freehold</option>
                                <option value="STOOL">Stool Land</option>
                                <option value="FAMILY">Family Land</option>
                                <option value="STATE">State Land</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Compliance Stage *</label>
                            <select name="compliance_stage" class="kt-select text-xs" required>
                                <option value="IN_DUE_DILIGENCE">1. Due Diligence</option>
                                <option value="PURCHASE_AND_REGISTRATION">2. Registration</option>
                                <option value="PERMITTING_AND_PRE_CONSTRUCTION">3. Permitting</option>
                                <option value="PROJECT_READY_FOR_BUILD" selected>4. Ready to Build</option>
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Showcase Photo</label>
                            <input type="file" name="image" class="kt-input text-[11px]" accept="image/*">
                        </div>
                    </div>
                    <div class="flex items-center gap-4 pt-1">
                        <label class="inline-flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" name="is_featured" value="1" class="kt-checkbox">
                            <span class="text-[11px] text-foreground font-medium">Featured</span>
                        </label>
                        <label class="inline-flex items-center gap-1 cursor-pointer">
                            <input type="checkbox" name="is_published" value="1" class="kt-checkbox" checked>
                            <span class="text-[11px] text-foreground font-medium">Publish Now</span>
                        </label>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary kt-btn-sm w-full justify-center">Create &amp; Publish Listing</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Master Land Compliance Register & State Machine Controls --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="kt-card-title">Ghana 4-Stage Land Compliance &amp; Parcel Register</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Enclave plots with spatial boundary overlap protection &amp; Lands Commission 30-day search verification</p>
            </div>

            {{-- Parcels Filter Bar --}}
            <form action="{{ route('real-estate.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="parcel_search" value="{{ request('parcel_search') }}" placeholder="Search plot / block..." class="kt-input kt-input-sm text-xs w-36">
                <select name="parcel_project_id" class="kt-select kt-select-sm text-xs w-36">
                    <option value="">All Projects</option>
                    @foreach($allProjects as $pr)
                        <option value="{{ $pr->id }}" @selected(request('parcel_project_id') == $pr->id)>{{ $pr->name }}</option>
                    @endforeach
                </select>
                <select name="parcel_stage" class="kt-select kt-select-sm text-xs w-32">
                    <option value="">All Stages</option>
                    <option value="IN_DUE_DILIGENCE" @selected(request('parcel_stage') === 'IN_DUE_DILIGENCE')>Due Diligence</option>
                    <option value="PURCHASE_AND_REGISTRATION" @selected(request('parcel_stage') === 'PURCHASE_AND_REGISTRATION')>Registration</option>
                    <option value="PERMITTING_AND_PRE_CONSTRUCTION" @selected(request('parcel_stage') === 'PERMITTING_AND_PRE_CONSTRUCTION')>Permitting</option>
                    <option value="PROJECT_READY_FOR_BUILD" @selected(request('parcel_stage') === 'PROJECT_READY_FOR_BUILD')>Ready to Build</option>
                </select>
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Filter</button>
                @if(request()->anyFilled(['parcel_search', 'parcel_project_id', 'parcel_stage']))
                    <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
                @endif
            </form>
        </div>

        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Plot &amp; Project</th>
                        <th>Tenure &amp; Size</th>
                        <th>Price (GHS)</th>
                        <th>Compliance Stage</th>
                        <th>Spatial Overlap</th>
                        <th>30-Day Search Rule</th>
                        <th>Boundary Security</th>
                        <th>Status / Client</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parcels as $parcel)
                        <tr>
                            <td>
                                <div class="font-bold text-foreground font-mono">{{ $parcel->plot_number }}</div>
                                <div class="text-xs text-muted-foreground">{{ $parcel->project?->name ?? 'Enclave' }} • Block {{ $parcel->block_number ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $parcel->tenure_type }}</span>
                                <div class="text-xs text-muted-foreground mt-0.5">{{ $parcel->size_dimension }} ({{ $parcel->acreage }} ac)</div>
                            </td>
                            <td class="font-semibold text-foreground text-xs">{{ $parcel->formattedPrice() }}</td>
                            <td>
                                @if($parcel->current_stage === 'IN_DUE_DILIGENCE')
                                    <span class="kt-badge kt-badge-xs kt-badge-warning">1. Due Diligence</span>
                                @elseif($parcel->current_stage === 'PURCHASE_AND_REGISTRATION')
                                    <span class="kt-badge kt-badge-xs kt-badge-primary">2. Registration</span>
                                @elseif($parcel->current_stage === 'PERMITTING_AND_PRE_CONSTRUCTION')
                                    <span class="kt-badge kt-badge-xs kt-badge-info">3. Permitting</span>
                                @elseif($parcel->current_stage === 'PROJECT_READY_FOR_BUILD')
                                    <span class="kt-badge kt-badge-xs kt-badge-success">4. Ready to Build</span>
                                @endif
                                @if($parcel->is_legacy)
                                    <span class="kt-badge kt-badge-xs kt-badge-secondary ml-1">Legacy</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $overlapConflict = $parcel->checkSpatialOverlap();
                                @endphp
                                @if($overlapConflict)
                                    <span class="kt-badge kt-badge-xs kt-badge-danger" title="Conflict with Plot {{ $overlapConflict->plot_number }}">
                                        <i class="ki-filled ki-cross-circle text-[10px] mr-1"></i>
                                        Overlap Warning
                                    </span>
                                @elseif($parcel->hasSpatialCoordinates())
                                    <span class="kt-badge kt-badge-xs kt-badge-success">
                                        <i class="ki-filled ki-check-circle text-[10px] mr-1"></i>
                                        No Overlap
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground">Unbounded</span>
                                @endif
                            </td>
                            <td>
                                @if($parcel->is_legacy)
                                    <span class="kt-badge kt-badge-xs kt-badge-secondary">Legacy Exempt</span>
                                @elseif(! $parcel->search_issue_date)
                                    <span class="kt-badge kt-badge-xs kt-badge-warning">Search Pending</span>
                                @elseif($parcel->isSearchExpired())
                                    <span class="kt-badge kt-badge-xs kt-badge-danger" title="Search report older than 30 days">
                                        <i class="ki-filled ki-information text-[10px] mr-1"></i>
                                        Expired (&gt;30d)
                                    </span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-success">
                                        Valid ({{ $parcel->searchDaysRemaining() }}d left)
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($parcel->hasUnsecuredBoundaryRisk())
                                    <span class="kt-badge kt-badge-xs kt-badge-danger" title="Unsecured boundaries detected. Vulnerable to physical encroachment.">
                                        <i class="ki-filled ki-shield-cross text-[10px] mr-1"></i>
                                        Encroachment Risk
                                    </span>
                                @elseif($parcel->pillars_and_wall_secured)
                                    <span class="kt-badge kt-badge-xs kt-badge-success">
                                        <i class="ki-filled ki-shield-tick text-[10px] mr-1"></i>
                                        Pillars Secured
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground">In Review</span>
                                @endif
                            </td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $parcel->status }}</span>
                                @if($parcel->allocation)
                                    <div class="text-xs text-foreground font-medium mt-0.5">{{ $parcel->allocation->client?->first_name }} {{ $parcel->allocation->client?->last_name }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Transition Stage Form --}}
                                    @if($parcel->current_stage === 'IN_DUE_DILIGENCE')
                                        <form action="{{ route('real-estate.parcels.transition', $parcel) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="target_stage" value="PURCHASE_AND_REGISTRATION">
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline" title="Advance to Registration">
                                                Advance &rarr;
                                            </button>
                                        </form>
                                    @elseif($parcel->current_stage === 'PURCHASE_AND_REGISTRATION')
                                        <form action="{{ route('real-estate.parcels.transition', $parcel) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="target_stage" value="PERMITTING_AND_PRE_CONSTRUCTION">
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline" title="Advance to Permitting">
                                                Advance &rarr;
                                            </button>
                                        </form>
                                    @elseif($parcel->current_stage === 'PERMITTING_AND_PRE_CONSTRUCTION')
                                        <form action="{{ route('real-estate.parcels.transition', $parcel) }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="target_stage" value="PROJECT_READY_FOR_BUILD">
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-success" title="Approve Ready to Build">
                                                Ready &check;
                                            </button>
                                        </form>
                                    @endif

                                    {{-- Edit Parcel --}}
                                    <a href="{{ route('real-estate.parcels.edit', $parcel) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                        Edit
                                    </a>

                                    {{-- Allocate Plot to Client --}}
                                    @if($parcel->status === 'available')
                                        <details class="relative inline-block text-start">
                                            <summary class="kt-btn kt-btn-xs kt-btn-primary cursor-pointer list-none">Allocate</summary>
                                            <div class="absolute right-0 mt-1 w-72 p-3 bg-card rounded-lg shadow-lg border border-border z-30 space-y-2">
                                                <div class="text-xs font-semibold text-foreground">Allocate Plot to Client</div>
                                                <form action="{{ route('real-estate.parcels.allocate', $parcel) }}" method="POST" class="space-y-2">
                                                    @csrf
                                                    <input type="text" name="client_name" class="kt-input kt-input-sm text-xs" placeholder="Client Full Name" required>
                                                    <input type="email" name="client_email" class="kt-input kt-input-sm text-xs" placeholder="Client Email" required>
                                                    <select name="number_of_installments" class="kt-select kt-select-sm text-xs" required>
                                                        <option value="1">1 Payment (Full Cash)</option>
                                                        <option value="3">3 Monthly Installments</option>
                                                        <option value="6">6 Monthly Installments</option>
                                                        <option value="12">12 Monthly Installments</option>
                                                    </select>
                                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Create Allocation</button>
                                                </form>
                                            </div>
                                        </details>
                                    @endif

                                    {{-- Order Surveyor / Materials from Vendor Marketplace --}}
                                    <details class="relative inline-block text-start">
                                        <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">Order Expert</summary>
                                        <div class="absolute right-0 mt-1 w-64 p-3 bg-card rounded-lg shadow-lg border border-border z-30 space-y-2">
                                            <div class="text-xs font-semibold text-foreground">Dispatch Vendor Marketplace Job</div>
                                            <form action="{{ route('real-estate.parcels.order-vendor', $parcel) }}" method="POST" class="space-y-2">
                                                @csrf
                                                <select name="category" class="kt-select kt-select-sm text-xs" required>
                                                    <option value="surveying">Cadastral / Boundary Surveyor</option>
                                                    <option value="land_registration">Lands Commission Title Lawyer</option>
                                                    <option value="architecture_engineering">Architect &amp; Structural Engineer</option>
                                                    <option value="building_materials">Building Materials (Sand, Cement)</option>
                                                </select>
                                                <input type="text" name="title" class="kt-input kt-input-sm text-xs" placeholder="e.g. Pillar Demarcation" required>
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Dispatch Job</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-6 text-sm text-muted-foreground">No land parcels matching filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($parcels->hasPages())
            <div class="p-4 border-t border-border">
                {{ $parcels->links() }}
            </div>
        @endif
    </div>

    {{-- Projects Register Grid --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="kt-card-title">Real Estate Enclaves &amp; Projects Directory</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Master planned developments and acreage registers</p>
            </div>
            <form action="{{ route('real-estate.index') }}" method="GET" class="flex items-center gap-2">
                <input type="text" name="project_search" value="{{ request('project_search') }}" placeholder="Search projects..." class="kt-input kt-input-sm text-xs w-44">
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Search</button>
                @if(request('project_search'))
                    <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Project Name &amp; Code</th>
                        <th>Location &amp; Region</th>
                        <th>Total Acreage</th>
                        <th>Plots</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $proj)
                        <tr>
                            <td>
                                <div class="font-bold text-foreground">{{ $proj->name }}</div>
                                <div class="font-mono text-xs text-muted-foreground">{{ $proj->code }}</div>
                            </td>
                            <td>
                                <div class="text-xs text-foreground">{{ $proj->location }}</div>
                                <div class="text-[11px] text-muted-foreground">{{ $proj->region }}</div>
                            </td>
                            <td class="font-mono text-xs">{{ $proj->total_acreage }} acres</td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $proj->parcels_count ?? $proj->parcels()->count() }} plots</span></td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-success uppercase">{{ $proj->status }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('real-estate.projects.edit', $proj) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-sm text-muted-foreground">No projects registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($projects->hasPages())
            <div class="p-4 border-t border-border">
                {{ $projects->links() }}
            </div>
        @endif
    </div>

    {{-- Public Property Listings Management --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="kt-card-title">Public Marketing Listings Catalog</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Manage customer-facing listings and showcase media</p>
            </div>
            <form action="{{ route('real-estate.index') }}" method="GET" class="flex items-center gap-2">
                <input type="text" name="listing_search" value="{{ request('listing_search') }}" placeholder="Search listings..." class="kt-input kt-input-sm text-xs w-44">
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Search</button>
                @if(request('listing_search'))
                    <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Listing Title &amp; Location</th>
                        <th>Price</th>
                        <th>Size &amp; Tenure</th>
                        <th>Compliance Stage</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($listings as $item)
                        <tr>
                            <td class="w-16">
                                @if($item->image_path)
                                    <img src="{{ Storage::url($item->image_path) }}" alt="{{ $item->title }}" class="h-10 w-14 object-cover rounded border border-border">
                                @else
                                    <div class="h-10 w-14 rounded bg-muted/40 border border-border flex items-center justify-center text-muted-foreground text-[10px]">
                                        No Image
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="font-bold text-foreground">{{ $item->title }}</div>
                                <div class="text-xs text-muted-foreground">{{ $item->location }}</div>
                            </td>
                            <td class="font-bold text-xs text-foreground">{{ $item->formattedPrice() }}</td>
                            <td>
                                <div class="text-xs font-mono">{{ $item->size_dimension }}</div>
                                <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ $item->tenure_type }}</span>
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-success">{{ str_replace('_', ' ', $item->compliance_stage) }}</span></td>
                            <td>
                                @if($item->is_published)
                                    <span class="kt-badge kt-badge-xs kt-badge-success">Published</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-secondary">Draft</span>
                                @endif
                                @if($item->is_featured)
                                    <span class="kt-badge kt-badge-xs kt-badge-warning ml-1">Featured</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('real-estate.listings.edit', $item) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No listings published yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($listings->hasPages())
            <div class="p-4 border-t border-border">
                {{ $listings->links() }}
            </div>
        @endif
    </div>

    {{-- Client Plot Allocations & Installment Ledger --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="kt-card-title">Client Plot Allocations &amp; Installment Payment Ledger</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Purchaser ledger, payment tracking &amp; official receipt generation</p>
            </div>
            <form action="{{ route('real-estate.index') }}" method="GET" class="flex items-center gap-2">
                <input type="text" name="allocation_search" value="{{ request('allocation_search') }}" placeholder="Search allocations..." class="kt-input kt-input-sm text-xs w-44">
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Search</button>
                @if(request('allocation_search'))
                    <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Allocation #</th>
                        <th>Client</th>
                        <th>Plot</th>
                        <th>Total Price</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Installments Schedule &amp; Receipts</th>
                        <th class="text-end">Pay Installment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allocations as $alloc)
                        <tr>
                            <td class="font-mono text-xs font-semibold">{{ $alloc->allocation_number }}</td>
                            <td>
                                <div class="font-semibold text-foreground">{{ $alloc->client?->first_name }} {{ $alloc->client?->last_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ $alloc->client?->email }}</div>
                            </td>
                            <td>
                                <div class="font-mono text-xs font-bold">{{ $alloc->parcel?->plot_number }}</div>
                                <div class="text-xs text-muted-foreground">{{ $alloc->parcel?->project?->name }}</div>
                            </td>
                            <td class="text-xs font-semibold">{{ $alloc->formattedTotalPrice() }}</td>
                            <td class="text-xs text-emerald-600 font-semibold">{{ $alloc->formattedAmountPaid() }}</td>
                            <td class="text-xs text-rose-600 font-semibold">{{ $alloc->formattedOutstandingBalance() }}</td>
                            <td>
                                <div class="space-y-1">
                                    @foreach($alloc->installments as $inst)
                                        <div class="flex items-center gap-2 text-[11px]">
                                            <span class="font-mono">#{{ $inst->installment_number }}:</span>
                                            <span>{{ $inst->formattedAmountDue() }}</span>
                                            <span class="text-muted-foreground">(Due: {{ $inst->due_date?->format('d/m/Y') }})</span>
                                            @if($inst->status === 'paid')
                                                <span class="kt-badge kt-badge-xs kt-badge-success">Paid</span>
                                                <a href="{{ route('real-estate.installments.receipt', $inst) }}" class="text-[10px] text-primary hover:underline font-semibold" title="View &amp; Print Official Receipt">
                                                    Receipt &rarr;
                                                </a>
                                            @else
                                                <span class="kt-badge kt-badge-xs kt-badge-warning">{{ $inst->status }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end">
                                @php
                                    $pendingInst = $alloc->installments->firstWhere('status', 'pending');
                                @endphp
                                @if($pendingInst)
                                    <form action="{{ route('real-estate.installments.pay', $pendingInst) }}" method="POST" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="number" step="0.01" min="1" name="amount_ghs" value="{{ $pendingInst->amount_due_pesewas / 100 }}" class="kt-input kt-input-sm w-24 text-xs" required>
                                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Record Payment</button>
                                    </form>
                                @else
                                    <span class="text-xs text-emerald-600 font-semibold">Fully Paid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-6 text-sm text-muted-foreground">No client allocations matching filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($allocations->hasPages())
            <div class="p-4 border-t border-border">
                {{ $allocations->links() }}
            </div>
        @endif
    </div>

    {{-- Private Document Vault --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="kt-card-title">Encrypted / Private Real Estate Document Vault (Cache-Control: private, no-store)</h3>
                <p class="text-xs text-muted-foreground mt-0.5">Title deeds, site plans, EPA clearances, and stamped indentures</p>
            </div>
            <form action="{{ route('real-estate.index') }}" method="GET" class="flex items-center gap-2">
                <input type="text" name="doc_search" value="{{ request('doc_search') }}" placeholder="Search documents..." class="kt-input kt-input-sm text-xs w-44">
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Search</button>
                @if(request('doc_search'))
                    <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Clear</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Plot #</th>
                        <th>Document Title &amp; Type</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Uploaded By</th>
                        <th>Created</th>
                        <th class="text-end">Secure Download</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <td class="font-mono text-xs font-bold">{{ $doc->parcel?->plot_number }}</td>
                            <td>
                                <div class="font-semibold text-foreground">{{ $doc->title }}</div>
                                <div class="text-xs text-muted-foreground uppercase font-mono">{{ $doc->document_type }} @if($doc->is_legacy) • <span class="text-amber-600">Historical Legacy</span> @endif</div>
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $doc->workflow_stage }}</span></td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-success">{{ $doc->verification_status }}</span></td>
                            <td class="text-xs">{{ $doc->uploader?->first_name }} {{ $doc->uploader?->last_name }}</td>
                            <td class="text-xs text-muted-foreground">{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('real-estate.documents.download', $doc) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    <i class="ki-filled ki-file-down text-xs"></i>
                                    Download Vault File
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No documents uploaded to vault yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
            <div class="p-4 border-t border-border">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
