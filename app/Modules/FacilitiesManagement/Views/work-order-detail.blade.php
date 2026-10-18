@extends('layouts.metronic.app')

@section('title', "Work Order: {$workOrder->work_order_number}")

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('facilities-management.index') }}" class="text-xs text-muted-foreground hover:text-primary">
                    &larr; Back to Facilities Hub
                </a>
                @if($workOrder->asset)
                    <span class="text-xs text-muted-foreground">•</span>
                    <a href="{{ route('facilities-management.assets.show', $workOrder->asset->uuid) }}" class="text-xs text-muted-foreground hover:text-primary">
                        View Asset {{ $workOrder->asset->asset_tag }}
                    </a>
                @endif
                @if($workOrder->building)
                    <span class="text-xs text-muted-foreground">•</span>
                    <a href="{{ route('facilities-management.buildings.show', $workOrder->building->uuid) }}" class="text-xs text-muted-foreground hover:text-primary">
                        View Building {{ $workOrder->building->code }}
                    </a>
                @endif
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ $workOrder->title }}</h1>
                <span class="kt-badge kt-badge-outline font-mono text-xs">{{ $workOrder->work_order_number }}</span>
                @if($workOrder->mode === 'mode_b_occupant_marketplace')
                    <span class="kt-badge kt-badge-primary text-xs">Mode B: Marketplace</span>
                @else
                    <span class="kt-badge kt-badge-outline text-xs">Mode A: Institutional</span>
                @endif
                <span class="kt-badge kt-badge-warning uppercase text-xs">{{ $workOrder->priority }}</span>
                @if($workOrder->status === 'completed')
                    <span class="kt-badge kt-badge-success uppercase text-xs">Completed</span>
                @elseif($workOrder->status === 'in_progress')
                    <span class="kt-badge kt-badge-info uppercase text-xs">In Progress</span>
                @elseif($workOrder->status === 'assigned')
                    <span class="kt-badge kt-badge-primary uppercase text-xs">Assigned</span>
                @elseif($workOrder->status === 'cancelled')
                    <span class="kt-badge kt-badge-danger uppercase text-xs">Cancelled</span>
                @else
                    <span class="kt-badge kt-badge-secondary uppercase text-xs">{{ $workOrder->status }}</span>
                @endif
            </div>
            <p class="text-xs text-muted-foreground mt-1">
                Category: {{ $categories[$workOrder->category] ?? ucfirst(str_replace('_', ' ', $workOrder->category)) }}
                • Target Due Date: {{ $workOrder->due_date?->format('d/m/Y') ?? 'N/A' }}
                • Created: {{ $workOrder->created_at?->format('d/m/Y H:i') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($workOrder->status !== 'completed' && $workOrder->status !== 'cancelled')
                {{-- Assign Technician Modal --}}
                <details class="relative inline-block text-start">
                    <summary class="kt-btn kt-btn-outline kt-btn-sm cursor-pointer list-none">
                        <i class="ki-filled ki-user text-xs mr-1"></i> Assign Technician
                    </summary>
                    <div class="absolute right-0 mt-2 w-72 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                        <div class="text-xs font-semibold text-foreground">Assign Responsible Technician</div>
                        <form action="{{ route('facilities-management.work-orders.assign', $workOrder->uuid) }}" method="POST" class="space-y-2.5">
                            @csrf
                            <div>
                                <label class="kt-form-label text-xs">Technician *</label>
                                <select name="assigned_technician_id" class="kt-select text-xs" required>
                                    <option value="">Select Technician</option>
                                    @foreach($technicians as $tech)
                                        <option value="{{ $tech->uuid }}" @selected($workOrder->assigned_technician_id === (string) $tech->uuid)>
                                            {{ $tech->first_name }} {{ $tech->last_name }} ({{ $tech->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-primary kt-btn-xs w-full justify-center">Update Assignment</button>
                        </form>
                    </div>
                </details>

                {{-- Status Change Dropdown --}}
                <details class="relative inline-block text-start">
                    <summary class="kt-btn kt-btn-outline kt-btn-sm cursor-pointer list-none">
                        Status &rarr;
                    </summary>
                    <div class="absolute right-0 mt-2 w-64 p-3 bg-card rounded-xl shadow-xl border border-border z-30 space-y-2">
                        <div class="text-xs font-semibold text-foreground">Change Status</div>
                        <form action="{{ route('facilities-management.work-orders.status', $workOrder->uuid) }}" method="POST" class="space-y-2">
                            @csrf
                            <select name="status" class="kt-select text-xs" required>
                                <option value="open" @selected($workOrder->status === 'open')>Open / Pending</option>
                                <option value="assigned" @selected($workOrder->status === 'assigned')>Assigned</option>
                                <option value="in_progress" @selected($workOrder->status === 'in_progress')>In Progress</option>
                                <option value="cancelled" @selected($workOrder->status === 'cancelled')>Cancelled</option>
                            </select>
                            <button type="submit" class="kt-btn kt-btn-outline kt-btn-xs w-full justify-center">Save Status</button>
                        </form>
                    </div>
                </details>

                {{-- Edit Work Order Modal --}}
                <details class="relative inline-block text-start">
                    <summary class="kt-btn kt-btn-outline kt-btn-sm cursor-pointer list-none">
                        <i class="ki-filled ki-pencil text-xs mr-1"></i> Edit Order
                    </summary>
                    <div class="absolute right-0 mt-2 w-80 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                        <div class="text-xs font-semibold text-foreground">Edit Work Order</div>
                        <form action="{{ route('facilities-management.work-orders.update', $workOrder->uuid) }}" method="POST" class="space-y-2.5">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="kt-form-label text-[11px]">Title *</label>
                                <input type="text" name="title" value="{{ $workOrder->title }}" class="kt-input kt-input-sm text-xs" required>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="kt-form-label text-[11px]">Category *</label>
                                    <select name="category" class="kt-select kt-select-sm text-xs" required>
                                        <option value="plumbing" @selected($workOrder->category === 'plumbing')>Plumbing</option>
                                        <option value="electrical" @selected($workOrder->category === 'electrical')>Electrical</option>
                                        <option value="hvac" @selected($workOrder->category === 'hvac')>HVAC</option>
                                        <option value="landscaping" @selected($workOrder->category === 'landscaping')>Landscaping</option>
                                        <option value="water_delivery" @selected($workOrder->category === 'water_delivery')>Water Delivery</option>
                                        <option value="general_maintenance" @selected($workOrder->category === 'general_maintenance')>General Maintenance</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="kt-form-label text-[11px]">Priority *</label>
                                    <select name="priority" class="kt-select kt-select-sm text-xs" required>
                                        <option value="low" @selected($workOrder->priority === 'low')>Low</option>
                                        <option value="medium" @selected($workOrder->priority === 'medium')>Medium</option>
                                        <option value="high" @selected($workOrder->priority === 'high')>High</option>
                                        <option value="emergency" @selected($workOrder->priority === 'emergency')>Emergency</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="kt-form-label text-[11px]">Building</label>
                                <select name="facility_building_id" class="kt-select kt-select-sm text-xs">
                                    <option value="">Unassigned</option>
                                    @foreach($buildings as $bld)
                                        <option value="{{ $bld->id }}" @selected($workOrder->facility_building_id === $bld->id)>{{ $bld->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="kt-form-label text-[11px]">Associated Asset</label>
                                <select name="facility_asset_id" class="kt-select kt-select-sm text-xs">
                                    <option value="">No specific asset</option>
                                    @foreach($assets as $ast)
                                        <option value="{{ $ast->id }}" @selected($workOrder->facility_asset_id === $ast->id)>{{ $ast->asset_tag }} - {{ $ast->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="kt-form-label text-[11px]">Due Date</label>
                                <input type="date" name="due_date" value="{{ $workOrder->due_date?->toDateString() }}" class="kt-input kt-input-sm text-xs">
                            </div>
                            <div>
                                <label class="kt-form-label text-[11px]">Description</label>
                                <textarea name="description" class="kt-input kt-input-sm text-xs" rows="2">{{ $workOrder->description }}</textarea>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-primary kt-btn-xs w-full justify-center">Save Changes</button>
                        </form>
                    </div>
                </details>

                {{-- Cancel Work Order Modal --}}
                <details class="relative inline-block text-start">
                    <summary class="kt-btn kt-btn-outline kt-btn-sm text-rose-500 hover:bg-rose-500/10 cursor-pointer list-none">
                        Cancel Order
                    </summary>
                    <div class="absolute right-0 mt-2 w-72 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                        <div class="text-xs font-semibold text-rose-500">Cancel Work Order</div>
                        <p class="text-[11px] text-muted-foreground">This will cancel the work order and any linked marketplace service job.</p>
                        <form action="{{ route('facilities-management.work-orders.cancel', $workOrder->uuid) }}" method="POST" class="space-y-2.5">
                            @csrf
                            <div>
                                <label class="kt-form-label text-[11px]">Reason for Cancellation</label>
                                <textarea name="cancellation_reason" class="kt-input kt-input-sm text-xs" rows="2" placeholder="e.g. Issue resolved independently, duplicate request..."></textarea>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-destructive kt-btn-xs w-full justify-center">Confirm Cancellation</button>
                        </form>
                    </div>
                </details>

                {{-- Complete Order Action --}}
                <details class="relative inline-block text-start">
                    <summary class="kt-btn kt-btn-success kt-btn-sm cursor-pointer list-none">
                        &check; Complete Work Order
                    </summary>
                    <div class="absolute right-0 mt-2 w-80 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                        <div class="text-sm font-semibold text-foreground">Sign Off &amp; Complete Work Order</div>
                        <form action="{{ route('facilities-management.work-orders.complete', $workOrder) }}" method="POST" class="space-y-2.5">
                            @csrf
                            <div>
                                <label class="kt-form-label text-xs">Completion Notes &amp; Findings *</label>
                                <textarea name="completion_notes" class="kt-input text-xs" rows="3" placeholder="Describe actions taken, parts replaced, and operational verification..." required></textarea>
                            </div>
                            <button type="submit" class="kt-btn kt-btn-success kt-btn-sm w-full justify-center">Sign Off Order</button>
                        </form>
                    </div>
                </details>
            @endif
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Work Order Metadata Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Requester</div>
            <div class="text-sm font-bold text-foreground mt-1">
                {{ $workOrder->requester ? $workOrder->requester->first_name . ' ' . $workOrder->requester->last_name : 'System Generated' }}
            </div>
            <div class="text-[11px] text-muted-foreground mt-0.5">{{ $workOrder->requester?->email ?? 'N/A' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Assigned Technician</div>
            <div class="text-sm font-bold text-foreground mt-1">
                {{ $workOrder->technician ? $workOrder->technician->first_name . ' ' . $workOrder->technician->last_name : 'Unassigned' }}
            </div>
            <div class="text-[11px] text-muted-foreground mt-0.5">{{ $workOrder->technician?->email ?? 'Pending allocation' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Target Facility</div>
            <div class="text-sm font-bold text-foreground mt-1">
                {{ $workOrder->building?->name ?? 'Occupant Residential Enclave' }}
            </div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Space: {{ $workOrder->space?->name ?? 'Common / Primary Area' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Associated Asset</div>
            @if($workOrder->asset)
                <a href="{{ route('facilities-management.assets.show', $workOrder->asset->uuid) }}" class="text-sm font-bold text-primary hover:underline mt-1 block">
                    {{ $workOrder->asset->name }}
                </a>
                <div class="text-[11px] text-muted-foreground font-mono">Tag: {{ $workOrder->asset->asset_tag }}</div>
            @else
                <div class="text-sm font-bold text-muted-foreground mt-1">General Civil / Structural</div>
                <div class="text-[11px] text-muted-foreground">No asset ledger link</div>
            @endif
        </div>
    </div>

    {{-- Description & Details Card --}}
    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title text-base font-semibold">Work Order Description &amp; Scope</h3>
        </div>
        <div class="kt-card-body p-5 space-y-4">
            <div>
                <div class="text-xs text-muted-foreground font-semibold uppercase mb-1">Issue / Maintenance Scope</div>
                <p class="text-sm text-foreground bg-muted/20 p-3 rounded-lg border border-border">
                    {{ $workOrder->description ?? 'No detailed description provided.' }}
                </p>
            </div>

            @if($workOrder->completion_notes)
                <div>
                    <div class="text-xs text-emerald-700 dark:text-emerald-400 font-semibold uppercase mb-1">Resolution &amp; Completion Notes</div>
                    <p class="text-sm text-foreground bg-emerald-500/10 p-3 rounded-lg border border-emerald-500/20">
                        {{ $workOrder->completion_notes }}
                    </p>
                    <div class="text-[11px] text-muted-foreground mt-1">
                        Completed at {{ $workOrder->completed_at?->format('d/m/Y H:i') }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- If Mode B: Companion Vendor Marketplace Service Job Card --}}
    @if($workOrder->serviceJob)
        <div class="kt-card border-primary/20 bg-primary/5">
            <div class="kt-card-header flex items-center justify-between">
                <div>
                    <h3 class="kt-card-title text-base font-semibold text-primary">Companion Vendor Marketplace Service Job</h3>
                    <p class="text-xs text-muted-foreground">This request was dispatched to external marketplace artisans via VendorMarketplace.</p>
                </div>
                <span class="kt-badge kt-badge-primary uppercase font-mono text-xs">{{ $workOrder->serviceJob->job_number }}</span>
            </div>
            <div class="kt-card-body p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-xs text-muted-foreground uppercase font-semibold">Job Title &amp; Category</div>
                    <div class="text-sm font-bold text-foreground mt-1">{{ $workOrder->serviceJob->title }}</div>
                    <div class="text-xs text-muted-foreground capitalize">{{ $workOrder->serviceJob->category }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground uppercase font-semibold">Marketplace Status</div>
                    <div class="text-sm font-bold text-foreground mt-1 uppercase">{{ $workOrder->serviceJob->status }}</div>
                    <div class="text-xs text-muted-foreground">Source Module: {{ $workOrder->serviceJob->source_module }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground uppercase font-semibold">Location / Address</div>
                    <div class="text-sm font-bold text-foreground mt-1">{{ $workOrder->serviceJob->location }}</div>
                    <div class="text-xs text-muted-foreground">Dispatched {{ $workOrder->serviceJob->created_at?->format('d/m/Y') }}</div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

