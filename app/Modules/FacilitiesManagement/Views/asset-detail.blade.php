@extends('layouts.metronic.app')

@section('title', "Asset: {$asset->name} ({$asset->asset_tag})")

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
                <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ $asset->name }}</h1>
                <span class="kt-badge kt-badge-outline font-mono text-xs">{{ $asset->asset_tag }}</span>
                @if($asset->status === 'operational')
                    <span class="kt-badge kt-badge-success text-xs uppercase">Operational</span>
                @elseif($asset->status === 'under_maintenance')
                    <span class="kt-badge kt-badge-warning text-xs uppercase">Under Maintenance</span>
                @elseif($asset->status === 'needs_repair')
                    <span class="kt-badge kt-badge-danger text-xs uppercase">Needs Repair</span>
                @else
                    <span class="kt-badge kt-badge-secondary text-xs uppercase">{{ $asset->status }}</span>
                @endif
                @if($asset->criticality === 'mission_critical')
                    <span class="kt-badge kt-badge-danger text-xs uppercase">Mission Critical</span>
                @elseif($asset->criticality === 'high')
                    <span class="kt-badge kt-badge-warning text-xs uppercase">High</span>
                @else
                    <span class="kt-badge kt-badge-secondary text-xs uppercase">{{ $asset->criticality }}</span>
                @endif
            </div>
            <p class="text-xs text-muted-foreground mt-1">
                Building: <a href="{{ route('facilities-management.buildings.show', $asset->building->uuid) }}" class="text-primary hover:underline font-semibold">{{ $asset->building?->name }}</a>
                • Room/Space: {{ $asset->space?->name ?? 'Common / Core' }}
                • Category: {{ $categories[$asset->category] ?? $asset->category }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <details class="relative inline-block text-start">
                <summary class="kt-btn kt-btn-outline kt-btn-sm cursor-pointer list-none">
                    <i class="ki-filled ki-pencil text-xs mr-1"></i> Edit Asset
                </summary>
                <div class="absolute right-0 mt-2 w-80 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                    <div class="text-sm font-semibold text-foreground">Edit Asset Record</div>
                    <form action="{{ route('facilities-management.assets.update', $asset->uuid) }}" method="POST" class="space-y-2.5">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="kt-form-label text-xs">Asset Name *</label>
                            <input type="text" name="name" value="{{ $asset->name }}" class="kt-input text-xs" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Category *</label>
                                <select name="category" class="kt-select text-xs" required>
                                    @foreach($categories as $catKey => $catLabel)
                                        <option value="{{ $catKey }}" @selected($asset->category === $catKey)>{{ $catLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Criticality *</label>
                                <select name="criticality" class="kt-select text-xs" required>
                                    @foreach($criticalityLevels as $critKey => $critLabel)
                                        <option value="{{ $critKey }}" @selected($asset->criticality === $critKey)>{{ $critLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Status *</label>
                                <select name="status" class="kt-select text-xs" required>
                                    @foreach($assetStatuses as $statusKey => $statusLabel)
                                        <option value="{{ $statusKey }}" @selected($asset->status === $statusKey)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Space / Room</label>
                                <select name="facility_space_id" class="kt-select text-xs">
                                    <option value="">Building Core</option>
                                    @foreach($spaces as $space)
                                        <option value="{{ $space->id }}" @selected($asset->facility_space_id === $space->id)>{{ $space->name }} ({{ $space->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Manufacturer</label>
                                <input type="text" name="manufacturer" value="{{ $asset->manufacturer }}" class="kt-input text-xs">
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Model Number</label>
                                <input type="text" name="model_number" value="{{ $asset->model_number }}" class="kt-input text-xs">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Serial Number</label>
                                <input type="text" name="serial_number" value="{{ $asset->serial_number }}" class="kt-input text-xs font-mono">
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Cost (GHS)</label>
                                <input type="number" step="0.01" min="0" name="purchase_cost_ghs" value="{{ $asset->purchase_cost_pesewas / 100 }}" class="kt-input text-xs">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Purchase Date</label>
                                <input type="date" name="purchase_date" value="{{ $asset->purchase_date?->toDateString() }}" class="kt-input text-xs">
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Warranty Expiry</label>
                                <input type="date" name="warranty_expiry_date" value="{{ $asset->warranty_expiry_date?->toDateString() }}" class="kt-input text-xs">
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary kt-btn-sm w-full justify-center">Update Asset</button>
                    </form>
                </div>
            </details>

            <form action="{{ route('facilities-management.assets.destroy', $asset->uuid) }}" method="POST" onsubmit="return confirm('Archive this asset?');">
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

    {{-- Stats & Technical Metadata Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Technical Specs</div>
            <div class="text-sm font-bold text-foreground mt-1">{{ $asset->manufacturer ?? 'Generic' }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Model: {{ $asset->model_number ?? 'N/A' }}</div>
            <div class="text-[11px] text-muted-foreground font-mono">S/N: {{ $asset->serial_number ?? 'N/A' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Financial &amp; Warranty</div>
            <div class="text-sm font-bold text-foreground mt-1">{{ $asset->formattedCost() }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Purchased: {{ $asset->purchase_date?->format('d/m/Y') ?? 'N/A' }}</div>
            <div class="text-[11px] text-muted-foreground">Warranty: {{ $asset->warranty_expiry_date?->format('d/m/Y') ?? 'N/A' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Location In Facility</div>
            <div class="text-sm font-bold text-foreground mt-1">{{ $asset->building?->name }}</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">Building Code: {{ $asset->building?->code }}</div>
            <div class="text-[11px] text-muted-foreground">Room: {{ $asset->space?->name ?? 'Common / Core Area' }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs text-muted-foreground uppercase font-semibold">Maintenance Summary</div>
            <div class="text-sm font-bold text-foreground mt-1">{{ $asset->schedules->count() }} Active PM Plans</div>
            <div class="text-[11px] text-muted-foreground mt-0.5">{{ $asset->workOrders->count() }} Total Work Orders</div>
            <div class="text-[11px] text-muted-foreground">Last Serviced: {{ $asset->last_serviced_at?->format('d/m/Y') ?? 'N/A' }}</div>
        </div>
    </div>

    {{-- Preventive Maintenance Schedules Section --}}
    <div class="kt-card">
        <div class="kt-card-header flex items-center justify-between">
            <h3 class="kt-card-title text-base font-semibold">Preventive Maintenance (PM) Schedules</h3>
            <details class="relative inline-block text-start">
                <summary class="kt-btn kt-btn-xs kt-btn-primary cursor-pointer list-none">+ Add PM Schedule</summary>
                <div class="absolute right-0 mt-2 w-72 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                    <div class="text-xs font-semibold text-foreground">New Schedule for {{ $asset->asset_tag }}</div>
                    <form action="{{ route('facilities-management.assets.schedules.store', $asset) }}" method="POST" class="space-y-2.5">
                        @csrf
                        <div>
                            <label class="kt-form-label text-xs">Schedule Title *</label>
                            <input type="text" name="title" class="kt-input text-xs" placeholder="e.g. Quarterly Filter & Oil Replacement" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Frequency *</label>
                                <select name="frequency" class="kt-select text-xs" required>
                                    <option value="monthly">Monthly (30d)</option>
                                    <option value="quarterly">Quarterly (90d)</option>
                                    <option value="biannual">Biannual (180d)</option>
                                    <option value="annual">Annual (365d)</option>
                                </select>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Interval (Days) *</label>
                                <input type="number" min="1" max="365" name="interval_days" value="90" class="kt-input text-xs" required>
                            </div>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Standard Operating Procedure / Notes</label>
                            <textarea name="description" class="kt-input text-xs" rows="2" placeholder="Tasks to execute during PM..."></textarea>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary kt-btn-xs w-full justify-center">Create Schedule</button>
                    </form>
                </div>
            </details>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Schedule Title</th>
                        <th>Frequency</th>
                        <th>Interval</th>
                        <th>Next Due Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($asset->schedules as $sched)
                        <tr>
                            <td>
                                <div class="text-xs font-semibold text-foreground">{{ $sched->title }}</div>
                                @if($sched->description)
                                    <div class="text-[11px] text-muted-foreground line-clamp-1">{{ $sched->description }}</div>
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
                            <td class="text-end">
                                <form action="{{ route('facilities-management.schedules.toggle', $sched->uuid) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline" title="Toggle active status">
                                        {{ $sched->is_active ? 'Pause' : 'Activate' }}
                                    </button>
                                </form>
                                <form action="{{ route('facilities-management.schedules.destroy', $sched->uuid) }}" method="POST" onsubmit="return confirm('Delete this schedule?');" class="inline ml-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" title="Delete Schedule">
                                        &times;
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-6 text-sm text-muted-foreground">No preventive maintenance schedules defined for this asset.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Maintenance Work Order History --}}
    <div class="kt-card">
        <div class="kt-card-header flex items-center justify-between">
            <h3 class="kt-card-title text-base font-semibold">Maintenance Work Order History</h3>
            <details class="relative inline-block text-start">
                <summary class="kt-btn kt-btn-xs kt-btn-outline cursor-pointer list-none">+ Dispatch Work Order</summary>
                <div class="absolute right-0 mt-2 w-80 p-4 bg-card rounded-xl shadow-xl border border-border z-30 space-y-3">
                    <div class="text-xs font-semibold text-foreground">Dispatch Work Order for {{ $asset->asset_tag }}</div>
                    <form action="{{ route('facilities-management.work-orders.institutional.store') }}" method="POST" class="space-y-2.5">
                        @csrf
                        <input type="hidden" name="facility_building_id" value="{{ $asset->facility_building_id }}">
                        <input type="hidden" name="facility_asset_id" value="{{ $asset->id }}">
                        <input type="hidden" name="category" value="{{ $asset->category }}">
                        <div>
                            <label class="kt-form-label text-xs">Work Order Title *</label>
                            <input type="text" name="title" class="kt-input text-xs" placeholder="e.g. Unscheduled Vibration Diagnostic" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="kt-form-label text-xs">Priority *</label>
                                <select name="priority" class="kt-select text-xs" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>
                            <div>
                                <label class="kt-form-label text-xs">Target Due Date</label>
                                <input type="date" name="due_date" value="{{ now()->addDays(3)->toDateString() }}" class="kt-input text-xs">
                            </div>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Issue Description</label>
                            <textarea name="description" class="kt-input text-xs" rows="2" placeholder="Details of the fault or maintenance required..."></textarea>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-primary kt-btn-xs w-full justify-center">Create Work Order</button>
                    </form>
                </div>
            </details>
        </div>
        <div class="kt-card-table kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border">
                <thead>
                    <tr>
                        <th>Order # &amp; Mode</th>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Technician</th>
                        <th>Completed Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($asset->workOrders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('facilities-management.work-orders.show', $order->uuid) }}" class="font-mono text-xs font-bold text-primary hover:underline">
                                    {{ $order->work_order_number }}
                                </a>
                            </td>
                            <td>
                                <div class="text-xs font-semibold text-foreground">{{ $order->title }}</div>
                                @if($order->completion_notes)
                                    <div class="text-[11px] text-emerald-600 truncate max-w-xs">Resolution: {{ $order->completion_notes }}</div>
                                @endif
                            </td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-warning uppercase">{{ $order->priority }}</span></td>
                            <td><span class="kt-badge kt-badge-xs kt-badge-primary uppercase">{{ $order->status }}</span></td>
                            <td class="text-xs">{{ $order->technician ? $order->technician->first_name . ' ' . $order->technician->last_name : 'Unassigned' }}</td>
                            <td class="text-xs text-muted-foreground">{{ $order->completed_at?->format('d/m/Y') ?? 'Incomplete' }}</td>
                            <td class="text-end">
                                <a href="{{ route('facilities-management.work-orders.show', $order->uuid) }}" class="kt-btn kt-btn-xs kt-btn-outline">
                                    View &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-6 text-sm text-muted-foreground">No maintenance work orders logged for this asset.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

