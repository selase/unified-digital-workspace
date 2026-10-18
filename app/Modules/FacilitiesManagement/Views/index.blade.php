@extends('layouts.metronic.app')

@section('title', 'Dual-Mode Facilities Management Hub')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Dual-Mode Facilities Management Hub</h1>
            <p class="text-sm text-muted-foreground">
                Institutional Asset Lifecycle &amp; Preventive Maintenance (Mode A) + Estate Occupant Artisan Marketplace Requests (Mode B)
            </p>
        </div>
        <div>
            <a href="{{ route('vendor-marketplace.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
                <i class="ki-filled ki-shop text-xs"></i>
                Vendor Marketplace &amp; Escrow Desk &rarr;
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
                Please address the following validation errors:
            </div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Metrics KPI Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Buildings</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $metrics['buildings_count'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Physical structures</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Total Assets</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $metrics['assets_count'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Tracked units</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Operational</div>
            <div class="text-xl font-bold text-emerald-600 mt-1">{{ $metrics['operational_assets'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Active equipment</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Open Work Orders</div>
            <div class="text-xl font-bold text-amber-600 mt-1">{{ $metrics['open_work_orders'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Mode A &amp; Mode B</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Occupant Orders</div>
            <div class="text-xl font-bold text-primary mt-1">{{ $metrics['occupant_marketplace_orders'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Marketplace artisans</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Due PM Schedules</div>
            <div class="text-xl font-bold text-rose-600 mt-1">{{ $metrics['due_schedules'] }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Due in 7 days</div>
        </div>
    </div>

    {{-- Action Forms Grid: 1. Register Building, 2. Register Asset, 3. Occupant Artisan Request --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Register Building --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">1. Register Building / Facility</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('facilities-management.buildings.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Building Name *</label>
                        <input type="text" name="name" class="kt-input" placeholder="e.g. Clinical Administration Block" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Code *</label>
                            <input type="text" name="code" class="kt-input font-mono" placeholder="BLD-ADM" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Floors *</label>
                            <input type="number" min="1" max="100" name="floors_count" class="kt-input" value="3" required>
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Total Area (sqm) *</label>
                        <input type="number" step="0.01" min="0" name="total_area_sqm" class="kt-input" placeholder="e.g. 1500.00" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Physical Address</label>
                        <input type="text" name="address" class="kt-input" placeholder="Legon Bypass Campus">
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Create Building</button>
                </form>
            </div>
        </div>

        {{-- Register Asset --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold">2. Register Equipment / Asset (Mode A)</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('facilities-management.assets.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Building *</label>
                            <select name="facility_building_id" class="kt-select" required>
                                <option value="">Select Building</option>
                                @foreach($buildings as $bld)
                                    <option value="{{ $bld->id }}">{{ $bld->name }} ({{ $bld->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Category *</label>
                            <select name="category" class="kt-select" required>
                                @foreach($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Asset Name *</label>
                        <input type="text" name="name" class="kt-input" placeholder="e.g. Carrier 50kW Central Chiller System" required>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Manufacturer</label>
                            <input type="text" name="manufacturer" class="kt-input" placeholder="Carrier">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Criticality *</label>
                            <select name="criticality" class="kt-select" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="mission_critical">Mission Critical</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Purchase Date</label>
                            <input type="date" name="purchase_date" class="kt-input">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Cost (GHS)</label>
                            <input type="number" step="0.01" min="0" name="purchase_cost_ghs" class="kt-input" placeholder="45000.00">
                        </div>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-outline w-full justify-center">Register Asset</button>
                </form>
            </div>
        </div>

        {{-- Estate Occupant Artisan Request (Mode B Marketplace) --}}
        <div class="kt-card border-primary/20 bg-primary/5">
            <div class="kt-card-header">
                <h3 class="kt-card-title text-sm font-semibold text-primary">3. Occupant Artisan Request (Mode B)</h3>
            </div>
            <div class="kt-card-body p-5">
                <form action="{{ route('facilities-management.work-orders.occupant.store') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Request Category *</label>
                        <select name="category" class="kt-select" required>
                            <option value="plumbing">Plumbing &amp; Leakage Artisan</option>
                            <option value="electrical">Electrical &amp; Inverter Artisan</option>
                            <option value="landscaping">Landscaping &amp; Grounds Care</option>
                            <option value="water_delivery">Potable Water Delivery Tanker</option>
                            <option value="general_maintenance">Civil &amp; General Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Order Title *</label>
                        <input type="text" name="title" class="kt-input" placeholder="e.g. Master Suite Bathroom Leakage Repair" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Estate / Apartment Location *</label>
                        <input type="text" name="location" class="kt-input" placeholder="e.g. Unit 4B, Emerald Court" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Issue Description *</label>
                        <textarea name="description" class="kt-input" rows="2" placeholder="Describe the service needed..." required></textarea>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full justify-center">
                        <i class="ki-filled ki-shop text-xs mr-1"></i>
                        Dispatch to Vendor Marketplace
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Master Asset Register Table --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col md:flex-row md:items-center justify-between gap-3">
            <h3 class="kt-card-title">Institutional Asset Register &amp; Lifecycle Ledger</h3>
            {{-- Asset Search & Filter Form --}}
            <form method="GET" action="{{ route('facilities-management.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="asset_search" value="{{ request('asset_search') }}" class="kt-input kt-input-sm text-xs w-44" placeholder="Search asset tag/name...">
                <select name="asset_building_id" class="kt-select kt-select-sm text-xs w-36">
                    <option value="">All Buildings</option>
                    @foreach($buildings as $bld)
                        <option value="{{ $bld->id }}" @selected((string) request('asset_building_id') === (string) $bld->id)>{{ $bld->name }}</option>
                    @endforeach
                </select>
                <select name="asset_category" class="kt-select kt-select-sm text-xs w-32">
                    <option value="">All Categories</option>
                    @foreach($categories as $catKey => $catLabel)
                        <option value="{{ $catKey }}" @selected(request('asset_category') === $catKey)>{{ $catLabel }}</option>
                    @endforeach
                </select>
                <select name="asset_status" class="kt-select kt-select-sm text-xs w-32">
                    <option value="">All Statuses</option>
                    @foreach($assetStatuses as $stKey => $stLabel)
                        <option value="{{ $stKey }}" @selected(request('asset_status') === $stKey)>{{ $stLabel }}</option>
                    @endforeach
                </select>
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Filter</button>
                @if(request()->anyFilled(['asset_search', 'asset_building_id', 'asset_category', 'asset_status']))
                    <a href="{{ route('facilities-management.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Asset Tag &amp; Name</th>
                        <th>Building / Space</th>
                        <th>Category</th>
                        <th>Criticality</th>
                        <th>Status</th>
                        <th>Purchase Cost</th>
                        <th class="text-end">Add Maintenance Schedule</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr>
                            <td>
                                <a href="{{ route('facilities-management.assets.show', $asset->uuid) }}" class="font-bold text-primary font-mono text-xs hover:underline">{{ $asset->asset_tag }}</a>
                                <div class="text-xs text-foreground font-semibold mt-0.5">
                                    <a href="{{ route('facilities-management.assets.show', $asset->uuid) }}" class="hover:text-primary">{{ $asset->name }}</a>
                                </div>
                                <div class="text-[11px] text-muted-foreground">{{ $asset->manufacturer ?? 'Generic' }} • {{ $asset->serial_number ?? 'S/N: N/A' }}</div>
                            </td>
                            <td>
                                @if($asset->building)
                                    <div class="text-xs font-semibold text-foreground">
                                        <a href="{{ route('facilities-management.buildings.show', $asset->building->uuid) }}" class="text-foreground hover:text-primary hover:underline">{{ $asset->building->name }}</a>
                                    </div>
                                @else
                                    <div class="text-xs text-muted-foreground italic">Unassigned</div>
                                @endif
                                <div class="text-[11px] text-muted-foreground">{{ $asset->space?->name ?? 'Common / Core' }}</div>
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $categories[$asset->category] ?? $asset->category }}</span></td>
                            <td>
                                @if($asset->criticality === 'mission_critical')
                                    <span class="kt-badge kt-badge-xs kt-badge-danger uppercase">Mission Critical</span>
                                @elseif($asset->criticality === 'high')
                                    <span class="kt-badge kt-badge-xs kt-badge-warning uppercase">High</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-secondary uppercase">{{ $asset->criticality }}</span>
                                @endif
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-success uppercase">{{ $asset->status }}</span></td>
                            <td class="text-xs font-semibold text-foreground">{{ $asset->formattedCost() }}</td>
                            <td class="text-end">
                                <details class="relative inline-block text-start">
                                    <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">+ Schedule PM</summary>
                                    <div class="absolute right-0 mt-1 w-64 p-3 bg-card rounded-lg shadow-lg border border-border z-30 space-y-2">
                                        <div class="text-xs font-semibold text-foreground">Add Preventive Maintenance Schedule</div>
                                        <form action="{{ route('facilities-management.assets.schedules.store', $asset) }}" method="POST" class="space-y-2">
                                            @csrf
                                            <input type="text" name="title" class="kt-input kt-input-sm text-xs" placeholder="e.g. Quarterly Chiller Filter Service" required>
                                            <select name="frequency" class="kt-select kt-select-sm text-xs" required>
                                                <option value="monthly">Monthly (30 Days)</option>
                                                <option value="quarterly">Quarterly (90 Days)</option>
                                                <option value="biannual">Biannual (180 Days)</option>
                                                <option value="annual">Annual (365 Days)</option>
                                            </select>
                                            <input type="number" min="1" max="365" name="interval_days" value="90" class="kt-input kt-input-sm text-xs" required>
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Create Schedule</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No assets found matching the criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assets->hasPages())
            <div class="p-4 border-t border-border">
                {{ $assets->links() }}
            </div>
        @endif
    </div>

    {{-- Work Orders Desk (Dual Mode: Institutional vs Occupant Marketplace) --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col md:flex-row md:items-center justify-between gap-3">
            <h3 class="kt-card-title">Dual-Mode Work Orders Desk</h3>
            {{-- Work Orders Search & Filter Form --}}
            <form method="GET" action="{{ route('facilities-management.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="work_order_search" value="{{ request('work_order_search') }}" class="kt-input kt-input-sm text-xs w-44" placeholder="Search order #/title...">
                <select name="work_order_building_id" class="kt-select kt-select-sm text-xs w-36">
                    <option value="">All Buildings</option>
                    @foreach($buildings as $bld)
                        <option value="{{ $bld->id }}" @selected((string) request('work_order_building_id') === (string) $bld->id)>{{ $bld->name }}</option>
                    @endforeach
                </select>
                <select name="work_order_priority" class="kt-select kt-select-sm text-xs w-28">
                    <option value="">All Priority</option>
                    <option value="low" @selected(request('work_order_priority') === 'low')>Low</option>
                    <option value="medium" @selected(request('work_order_priority') === 'medium')>Medium</option>
                    <option value="high" @selected(request('work_order_priority') === 'high')>High</option>
                    <option value="emergency" @selected(request('work_order_priority') === 'emergency')>Emergency</option>
                </select>
                <select name="work_order_status" class="kt-select kt-select-sm text-xs w-32">
                    <option value="">All Statuses</option>
                    <option value="open" @selected(request('work_order_status') === 'open')>Open</option>
                    <option value="assigned" @selected(request('work_order_status') === 'assigned')>Assigned</option>
                    <option value="in_progress" @selected(request('work_order_status') === 'in_progress')>In Progress</option>
                    <option value="completed" @selected(request('work_order_status') === 'completed')>Completed</option>
                    <option value="cancelled" @selected(request('work_order_status') === 'cancelled')>Cancelled</option>
                </select>
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Filter</button>
                @if(request()->anyFilled(['work_order_search', 'work_order_building_id', 'work_order_priority', 'work_order_status']))
                    <a href="{{ route('facilities-management.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Order # &amp; Mode</th>
                        <th>Title &amp; Category</th>
                        <th>Building / Location</th>
                        <th>Requester</th>
                        <th>Priority &amp; Status</th>
                        <th>Due Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workOrders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('facilities-management.work-orders.show', $order->uuid) }}" class="font-mono text-xs font-bold text-primary hover:underline">
                                    {{ $order->work_order_number }}
                                </a>
                                @if($order->mode === 'mode_b_occupant_marketplace')
                                    <span class="kt-badge kt-badge-xs kt-badge-primary">Mode B: Marketplace</span>
                                    @if($order->serviceJob)
                                        <div class="text-[10px] text-muted-foreground font-mono mt-0.5">Job: {{ $order->serviceJob->job_number }}</div>
                                    @endif
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-outline">Mode A: Institutional</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('facilities-management.work-orders.show', $order->uuid) }}" class="font-semibold text-foreground text-xs hover:text-primary">
                                    {{ $order->title }}
                                </a>
                                <div class="text-xs text-muted-foreground capitalize">{{ str_replace('_', ' ', $order->category) }}</div>
                            </td>
                            <td class="text-xs">
                                {{ $order->building?->name ?? 'Occupant Enclave' }}
                            </td>
                            <td>
                                <div class="text-xs font-semibold text-foreground">{{ $order->requester?->first_name }} {{ $order->requester?->last_name }}</div>
                            </td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-warning uppercase">{{ $order->priority }}</span>
                                <span class="kt-badge kt-badge-xs kt-badge-primary uppercase ml-1">{{ $order->status }}</span>
                            </td>
                            <td class="text-xs text-muted-foreground">{{ $order->due_date?->format('d/m/Y') ?? 'N/A' }}</td>
                            <td class="text-end space-x-1">
                                <a href="{{ route('facilities-management.work-orders.show', $order->uuid) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    View &rarr;
                                </a>
                                @if($order->status !== 'completed' && $order->status !== 'cancelled')
                                    <details class="relative inline-block text-start">
                                        <summary class="kt-btn kt-btn-xs kt-btn-success cursor-pointer list-none">&check; Complete</summary>
                                        <div class="absolute right-0 mt-1 w-64 p-3 bg-card rounded-lg shadow-lg border border-border z-30 space-y-2">
                                            <div class="text-xs font-semibold text-foreground">Sign Off Work Order</div>
                                            <form action="{{ route('facilities-management.work-orders.complete', $order) }}" method="POST" class="space-y-2">
                                                @csrf
                                                <textarea name="completion_notes" class="kt-input kt-input-sm text-xs" rows="2" placeholder="Sign-off / resolution notes..." required></textarea>
                                                <button type="submit" class="kt-btn kt-btn-xs kt-btn-success w-full justify-center">Mark Resolved</button>
                                            </form>
                                        </div>
                                    </details>
                                @elseif($order->status === 'completed')
                                    <span class="text-xs text-emerald-600 font-semibold">Done</span>
                                @else
                                    <span class="text-xs text-rose-500 font-semibold">Cancelled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No work orders found matching the criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($workOrders->hasPages())
            <div class="p-4 border-t border-border">
                {{ $workOrders->links() }}
            </div>
        @endif
    </div>

    {{-- Spaces, Rooms & Wards Register Table --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col md:flex-row md:items-center justify-between gap-3">
            <h3 class="kt-card-title">Spaces, Rooms &amp; Wards Register</h3>
            {{-- Spaces Search & Filter Form --}}
            <form method="GET" action="{{ route('facilities-management.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="space_search" value="{{ request('space_search') }}" class="kt-input kt-input-sm text-xs w-44" placeholder="Search code/name...">
                <select name="space_building_id" class="kt-select kt-select-sm text-xs w-36">
                    <option value="">All Buildings</option>
                    @foreach($buildings as $bld)
                        <option value="{{ $bld->id }}" @selected((string) request('space_building_id') === (string) $bld->id)>{{ $bld->name }}</option>
                    @endforeach
                </select>
                <select name="space_type" class="kt-select kt-select-sm text-xs w-36">
                    <option value="">All Space Types</option>
                    @foreach($spaceTypes as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}" @selected(request('space_type') === $typeKey)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Filter</button>
                @if(request()->anyFilled(['space_search', 'space_building_id', 'space_type']))
                    <a href="{{ route('facilities-management.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Code &amp; Name</th>
                        <th>Building</th>
                        <th>Floor</th>
                        <th>Space Type</th>
                        <th>Occupant</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($spaces as $sp)
                        <tr>
                            <td>
                                <span class="font-mono text-xs font-bold text-foreground">{{ $sp->code }}</span>
                                <div class="text-xs text-foreground font-semibold mt-0.5">{{ $sp->name }}</div>
                            </td>
                            <td class="text-xs">
                                @if($sp->building)
                                    <a href="{{ route('facilities-management.buildings.show', $sp->building->uuid) }}" class="text-foreground hover:text-primary hover:underline font-medium">
                                        {{ $sp->building->name }}
                                    </a>
                                @else
                                    <span class="text-muted-foreground italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="text-xs">Floor {{ $sp->floor_number }}</td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline">{{ $spaceTypes[$sp->space_type] ?? $sp->space_type }}</span>
                            </td>
                            <td class="text-xs">
                                @if($sp->occupant)
                                    <span class="font-semibold text-foreground">{{ $sp->occupant->first_name }} {{ $sp->occupant->last_name }}</span>
                                @else
                                    <span class="text-muted-foreground italic">Common / Unassigned</span>
                                @endif
                            </td>
                            <td class="text-end space-x-1">
                                <details class="relative inline-block text-start">
                                    <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">Edit</summary>
                                    <div class="absolute right-0 mt-1 w-72 p-3 bg-card rounded-lg shadow-lg border border-border z-30 space-y-2">
                                        <div class="text-xs font-semibold text-foreground">Edit Space {{ $sp->code }}</div>
                                        <form action="{{ route('facilities-management.spaces.update', $sp->uuid) }}" method="POST" class="space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <label class="kt-form-label text-[11px]">Space Name *</label>
                                                <input type="text" name="name" value="{{ $sp->name }}" class="kt-input kt-input-sm text-xs" required>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="kt-form-label text-[11px]">Code *</label>
                                                    <input type="text" name="code" value="{{ $sp->code }}" class="kt-input kt-input-sm text-xs font-mono" required>
                                                </div>
                                                <div>
                                                    <label class="kt-form-label text-[11px]">Floor # *</label>
                                                    <input type="number" min="0" max="120" name="floor_number" value="{{ $sp->floor_number }}" class="kt-input kt-input-sm text-xs" required>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="kt-form-label text-[11px]">Type *</label>
                                                <select name="space_type" class="kt-select kt-select-sm text-xs" required>
                                                    @foreach($spaceTypes as $typeKey => $typeLabel)
                                                        <option value="{{ $typeKey }}" @selected($sp->space_type === $typeKey)>{{ $typeLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="kt-form-label text-[11px]">Occupant Lead</label>
                                                <select name="occupant_user_id" class="kt-select kt-select-sm text-xs">
                                                    <option value="">None / Common Area</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->uuid }}" @selected($sp->occupant_user_id === (string) $user->uuid)>{{ $user->first_name }} {{ $user->last_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Update Space</button>
                                        </form>
                                    </div>
                                </details>
                                <form action="{{ route('facilities-management.spaces.destroy', $sp->uuid) }}" method="POST" onsubmit="return confirm('Remove this space?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" title="Delete Space">&times;</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-sm text-muted-foreground">No spaces found matching the criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($spaces->hasPages())
            <div class="p-4 border-t border-border">
                {{ $spaces->links() }}
            </div>
        @endif
    </div>

    {{-- Preventive Maintenance Schedules Register Table --}}
    <div class="kt-card">
        <div class="kt-card-header flex flex-col md:flex-row md:items-center justify-between gap-3">
            <h3 class="kt-card-title">Preventive Maintenance Schedules Ledger</h3>
            {{-- Schedules Search & Filter Form --}}
            <form method="GET" action="{{ route('facilities-management.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="schedule_search" value="{{ request('schedule_search') }}" class="kt-input kt-input-sm text-xs w-44" placeholder="Search title/desc...">
                <select name="schedule_frequency" class="kt-select kt-select-sm text-xs w-32">
                    <option value="">All Frequency</option>
                    <option value="daily" @selected(request('schedule_frequency') === 'daily')>Daily</option>
                    <option value="weekly" @selected(request('schedule_frequency') === 'weekly')>Weekly</option>
                    <option value="monthly" @selected(request('schedule_frequency') === 'monthly')>Monthly</option>
                    <option value="quarterly" @selected(request('schedule_frequency') === 'quarterly')>Quarterly</option>
                    <option value="biannual" @selected(request('schedule_frequency') === 'biannual')>Biannual</option>
                    <option value="annual" @selected(request('schedule_frequency') === 'annual')>Annual</option>
                </select>
                <select name="schedule_status" class="kt-select kt-select-sm text-xs w-28">
                    <option value="">All Status</option>
                    <option value="active" @selected(request('schedule_status') === 'active')>Active</option>
                    <option value="paused" @selected(request('schedule_status') === 'paused')>Paused</option>
                </select>
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary">Filter</button>
                @if(request()->anyFilled(['schedule_search', 'schedule_frequency', 'schedule_status']))
                    <a href="{{ route('facilities-management.index') }}" class="kt-btn kt-btn-xs kt-btn-outline">Reset</a>
                @endif
            </form>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Schedule Title</th>
                        <th>Associated Asset</th>
                        <th>Frequency</th>
                        <th>Interval</th>
                        <th>Next Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $sched)
                        <tr>
                            <td>
                                <div class="text-xs font-semibold text-foreground">{{ $sched->title }}</div>
                                @if($sched->description)
                                    <div class="text-[11px] text-muted-foreground line-clamp-1">{{ $sched->description }}</div>
                                @endif
                            </td>
                            <td>
                                @if($sched->asset)
                                    <a href="{{ route('facilities-management.assets.show', $sched->asset->uuid) }}" class="font-mono text-xs font-bold text-primary hover:underline">
                                        {{ $sched->asset->asset_tag }}
                                    </a>
                                    <div class="text-[11px] text-muted-foreground">{{ $sched->asset->name }} ({{ $sched->asset->building?->name ?? 'Unassigned' }})</div>
                                @else
                                    <span class="text-xs text-muted-foreground italic">N/A</span>
                                @endif
                            </td>
                            <td class="text-xs uppercase font-medium">{{ $sched->frequency }}</td>
                            <td class="text-xs">{{ $sched->interval_days }} Days</td>
                            <td class="text-xs font-mono font-semibold text-foreground">{{ $sched->next_due_date?->format('d/m/Y') ?? 'Pending' }}</td>
                            <td>
                                @if($sched->is_active)
                                    <span class="kt-badge kt-badge-xs kt-badge-success">Active</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-secondary">Paused</span>
                                @endif
                            </td>
                            <td class="text-end space-x-1">
                                <form action="{{ route('facilities-management.schedules.toggle', $sched->uuid) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline" title="Toggle active status">
                                        {{ $sched->is_active ? 'Pause' : 'Activate' }}
                                    </button>
                                </form>
                                <details class="relative inline-block text-start">
                                    <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">Edit</summary>
                                    <div class="absolute right-0 mt-1 w-64 p-3 bg-card rounded-lg shadow-lg border border-border z-30 space-y-2">
                                        <div class="text-xs font-semibold text-foreground">Edit Schedule</div>
                                        <form action="{{ route('facilities-management.schedules.update', $sched->uuid) }}" method="POST" class="space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <label class="kt-form-label text-[11px]">Title *</label>
                                                <input type="text" name="title" value="{{ $sched->title }}" class="kt-input kt-input-sm text-xs" required>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="kt-form-label text-[11px]">Frequency *</label>
                                                    <select name="frequency" class="kt-select kt-select-sm text-xs" required>
                                                        <option value="daily" @selected($sched->frequency === 'daily')>Daily</option>
                                                        <option value="weekly" @selected($sched->frequency === 'weekly')>Weekly</option>
                                                        <option value="monthly" @selected($sched->frequency === 'monthly')>Monthly</option>
                                                        <option value="quarterly" @selected($sched->frequency === 'quarterly')>Quarterly</option>
                                                        <option value="biannual" @selected($sched->frequency === 'biannual')>Biannual</option>
                                                        <option value="annual" @selected($sched->frequency === 'annual')>Annual</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="kt-form-label text-[11px]">Interval (d) *</label>
                                                    <input type="number" min="1" max="365" name="interval_days" value="{{ $sched->interval_days }}" class="kt-input kt-input-sm text-xs" required>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="kt-form-label text-[11px]">Description</label>
                                                <textarea name="description" class="kt-input kt-input-sm text-xs" rows="2">{{ $sched->description }}</textarea>
                                            </div>
                                            <button type="submit" class="kt-btn kt-btn-xs kt-btn-primary w-full justify-center">Save Changes</button>
                                        </form>
                                    </div>
                                </details>
                                <form action="{{ route('facilities-management.schedules.destroy', $sched->uuid) }}" method="POST" onsubmit="return confirm('Delete this schedule?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" title="Delete Schedule">&times;</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No maintenance schedules found matching the criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($schedules->hasPages())
            <div class="p-4 border-t border-border">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

