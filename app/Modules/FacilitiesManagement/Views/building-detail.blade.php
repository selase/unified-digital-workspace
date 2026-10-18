@extends('layouts.metronic.app')

@section('title', "Building: {$building->name}")

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('facilities-management.index') }}" class="text-xs text-muted-foreground hover:text-primary">
                    &larr; Back to Facilities Hub
                </a>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ $building->name }}</h1>
                <span class="kt-badge kt-badge-outline font-mono text-xs">{{ $building->code }}</span>
                @if($building->status === 'active')
                    <span class="kt-badge kt-badge-success text-xs">Active</span>
                @elseif($building->status === 'maintenance')
                    <span class="kt-badge kt-badge-warning text-xs">Maintenance</span>
                @else
                    <span class="kt-badge kt-badge-secondary text-xs">{{ ucfirst($building->status) }}</span>
                @endif
            </div>
            <p class="text-xs text-muted-foreground mt-1">
                {{ $building->address ?? 'No physical address specified' }} • {{ $building->floors_count }} Floors • {{ number_format((float) $building->total_area_sqm, 2) }} sqm
            </p>
        </div>

        <div class="flex items-center gap-2">
            <details class="relative inline-block text-start">
                <summary class="kt-btn kt-btn-outline kt-btn-sm cursor-pointer list-none">
                    <i class="ki-filled ki-pencil text-xs mr-1"></i> Edit Building
                </summary>
                <div class="absolute right-0 mt-2 w-80 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                    <div class="text-sm font-semibold text-foreground">Edit Building Charter</div>
                    <form action="{{ route('facilities-management.buildings.update', $building->uuid) }}" method="POST" class="space-y-2.5">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="kt-form-label text-xs">Building Name *</label>
                            <input type="text" name="name" value="{{ $building->name }}" class="kt-input text-xs" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Code *</label>
                                <input type="text" name="code" value="{{ $building->code }}" class="kt-input text-xs font-mono" required>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Floors *</label>
                                <input type="number" min="1" max="120" name="floors_count" value="{{ $building->floors_count }}" class="kt-input text-xs" required>
                            </div>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Total Area (sqm) *</label>
                            <input type="number" step="0.01" min="0" name="total_area_sqm" value="{{ $building->total_area_sqm }}" class="kt-input text-xs" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Status *</label>
                            <select name="status" class="kt-select text-xs" required>
                                @foreach($buildingStatuses as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" @selected($building->status === $statusKey)>{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Manager</label>
                            <select name="manager_id" class="kt-select text-xs">
                                <option value="">No manager assigned</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}" @selected($building->manager_id === (string) $user->uuid)>
                                        {{ $user->first_name }} {{ $user->last_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Address</label>
                            <input type="text" name="address" value="{{ $building->address }}" class="kt-input text-xs">
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary kt-btn-sm w-full justify-center">Update Building</button>
                    </form>
                </div>
            </details>

            <form action="{{ route('facilities-management.buildings.destroy', $building->uuid) }}" method="POST" onsubmit="return confirm('Archive this building?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="kt-btn kt-btn-outline kt-btn-sm text-destructive hover:bg-destructive/10">
                    <i class="ki-filled ki-trash text-xs mr-1"></i> Archive
                </button>
            </form>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('message'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm flex items-center gap-2">
            <i class="ki-filled ki-check-circle text-base"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Total Spaces</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $building->spaces->count() }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Rooms &amp; Wards</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Tracked Assets</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $building->assets->count() }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Equipment in structure</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Building Manager</div>
            <div class="text-sm font-bold text-foreground mt-1">
                {{ $building->manager ? $building->manager->first_name . ' ' . $building->manager->last_name : 'Unassigned' }}
            </div>
            <div class="text-[11px] text-muted-foreground mt-0.5">{{ $building->manager?->email ?? 'N/A' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Recent Work Orders</div>
            <div class="text-xl font-bold text-foreground mt-1">{{ $workOrders->count() }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Maintenance records</div>
        </div>
    </div>

    {{-- Spaces Management Section --}}
    <div class="kt-card">
        <div class="kt-card-header flex items-center justify-between">
            <h3 class="kt-card-title text-base font-semibold">Spaces, Wards &amp; Rooms</h3>
            <details class="relative inline-block text-start">
                <summary class="kt-btn kt-btn-xs kt-btn-primary cursor-pointer list-none">+ Add Space</summary>
                <div class="absolute right-0 mt-2 w-72 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                    <div class="text-xs font-semibold text-foreground">Add Space to {{ $building->name }}</div>
                    <form action="{{ route('facilities-management.buildings.spaces.store', $building->uuid) }}" method="POST" class="space-y-2.5">
                        @csrf
                        <div>
                            <label class="kt-form-label text-xs">Space Name *</label>
                            <input type="text" name="name" class="kt-input text-xs" placeholder="e.g. Diagnostic Radiology Lab" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Code *</label>
                                <input type="text" name="code" class="kt-input text-xs font-mono" placeholder="RAD-01" required>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Floor # *</label>
                                <input type="number" min="0" max="120" name="floor_number" value="1" class="kt-input text-xs" required>
                            </div>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Space Type *</label>
                            <select name="space_type" class="kt-select text-xs" required>
                                @foreach($spaceTypes as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Occupant / Staff Lead</label>
                            <select name="occupant_user_id" class="kt-select text-xs">
                                <option value="">None / Common Area</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->uuid }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary kt-btn-xs w-full justify-center">Create Space</button>
                    </form>
                </div>
            </details>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Code &amp; Name</th>
                        <th>Floor</th>
                        <th>Space Type</th>
                        <th>Occupant</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($building->spaces as $space)
                        <tr>
                            <td>
                                <span class="font-mono text-xs font-bold text-foreground">{{ $space->code }}</span>
                                <div class="text-xs text-foreground font-semibold mt-0.5">{{ $space->name }}</div>
                            </td>
                            <td class="text-xs">Floor {{ $space->floor_number }}</td>
                            <td>
                                <span class="kt-badge kt-badge-xs kt-badge-outline">{{ $spaceTypes[$space->space_type] ?? $space->space_type }}</span>
                            </td>
                            <td class="text-xs">
                                @if($space->occupant)
                                    <span class="font-semibold text-foreground">{{ $space->occupant->first_name }} {{ $space->occupant->last_name }}</span>
                                @else
                                    <span class="text-muted-foreground italic">Common / Unassigned</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('facilities-management.spaces.destroy', $space->uuid) }}" method="POST" onsubmit="return confirm('Remove this space?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" title="Delete Space">
                                        &times;
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-6 text-sm text-muted-foreground">No spaces or rooms mapped to this building yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Assets in Building --}}
    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title text-base font-semibold">Equipment &amp; Assets Located Here</h3>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Asset Tag &amp; Name</th>
                        <th>Room / Space</th>
                        <th>Category</th>
                        <th>Criticality</th>
                        <th>Status</th>
                        <th class="text-end">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($building->assets as $asset)
                        <tr>
                            <td>
                                <a href="{{ route('facilities-management.assets.show', $asset->uuid) }}" class="font-mono text-xs font-bold text-primary hover:underline">
                                    {{ $asset->asset_tag }}
                                </a>
                                <div class="text-xs text-foreground font-semibold mt-0.5">{{ $asset->name }}</div>
                            </td>
                            <td class="text-xs">{{ $asset->space?->name ?? 'Building Core' }}</td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $categories[$asset->category] ?? $asset->category }}</span></td>
                            <td>
                                @if($asset->criticality === 'mission_critical')
                                    <span class="kt-badge kt-badge-xs kt-badge-danger uppercase">Mission Critical</span>
                                @else
                                    <span class="kt-badge kt-badge-xs kt-badge-secondary uppercase">{{ $asset->criticality }}</span>
                                @endif
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-success uppercase">{{ $asset->status }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('facilities-management.assets.show', $asset->uuid) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    View &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-sm text-muted-foreground">No assets mapped to this building yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Work Orders in Building --}}
    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title text-base font-semibold">Recent Work Orders in {{ $building->name }}</h3>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Title &amp; Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Requester</th>
                        <th>Technician</th>
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
                            </td>
                            <td>
                                <div class="text-xs font-semibold text-foreground">{{ $order->title }}</div>
                                <div class="text-[11px] text-muted-foreground capitalize">{{ str_replace('_', ' ', $order->category) }}</div>
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-warning uppercase">{{ $order->priority }}</span></td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-primary uppercase">{{ $order->status }}</span></td>
                            <td class="text-xs">{{ $order->requester?->first_name }} {{ $order->requester?->last_name }}</td>
                            <td class="text-xs">{{ $order->technician ? $order->technician->first_name . ' ' . $order->technician->last_name : 'Unassigned' }}</td>
                            <td class="text-end">
                                <a href="{{ route('facilities-management.work-orders.show', $order->uuid) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    View &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No work orders recorded for this building.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

