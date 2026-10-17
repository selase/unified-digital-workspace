@extends('layouts.metronic.app')

@section('title', 'Quality Performance Alerts — Quality Monitoring')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Quality Performance Alerts"
        description="Active threshold violations, escalation chains, and clinical indicator breach tracking."
    >
        <x-slot:actions>
            <a href="{{ route('quality-monitoring.index') }}" class="kt-btn kt-btn-outline">
                Back to Hub
            </a>
            <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline">
                View Workplans
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Filter Toolbar & Alert Table Card --}}
    <x-metronic.card title="Alert Register" subtitle="Filter and triage quality alerts across clinical and operational domains">
        {{-- Toolbar --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
            <form method="GET" action="{{ route('quality-monitoring.alerts.index') }}" class="flex flex-wrap items-center gap-2.5">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search by alert type..."
                    class="kt-input min-w-[240px] text-xs"
                />
                <select name="status" onchange="this.form.submit()" class="kt-select min-w-[150px] text-xs">
                    <option value="">All Statuses</option>
                    <option value="open" @selected($status === 'open')>Open</option>
                    <option value="acknowledged" @selected($status === 'acknowledged')>Acknowledged</option>
                    <option value="resolved" @selected($status === 'resolved')>Resolved</option>
                </select>
                <button type="submit" class="kt-btn kt-btn-primary py-1 px-3 text-xs">
                    Filter
                </button>
                @if($search !== '' || $status !== '')
                    <a href="{{ route('quality-monitoring.alerts.index') }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Alerts Table --}}
        <div class="overflow-x-auto kt-scrollable-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-left">
                <thead>
                    <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Associated Workplan</th>
                        <th class="py-3 px-3">Clinical / Operational KPI</th>
                        <th class="py-3 px-3 text-center">Escalation</th>
                        <th class="py-3 px-3">Generated</th>
                        <th class="py-3 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs">
                    @forelse($alerts as $alert)
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-3">
                                <span class="font-bold text-foreground uppercase tracking-wider">
                                    {{ str_replace('_', ' ', $alert->type) }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $alertBadge = match($alert->status) {
                                        'open' => 'destructive',
                                        'acknowledged' => 'warning',
                                        'resolved' => 'success',
                                        default => 'outline',
                                    };
                                @endphp
                                <x-metronic.badge :variant="$alertBadge" size="sm">
                                    {{ ucfirst($alert->status) }}
                                </x-metronic.badge>
                            </td>
                            <td class="py-3 px-3">
                                @if($alert->workplan)
                                    <a href="{{ route('quality-monitoring.workplans.show', $alert->workplan) }}" class="font-semibold text-primary hover:underline">
                                        {{ $alert->workplan->title }}
                                    </a>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-muted-foreground">
                                {{ $alert->kpi?->name ?: 'Institutional Threshold' }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($alert->escalation_level > 0)
                                    <x-metronic.badge variant="warning" size="sm">
                                        Level {{ $alert->escalation_level }}
                                    </x-metronic.badge>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-muted-foreground">
                                {{ $alert->created_at?->diffForHumans() }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                @if($alert->status === 'open')
                                    <form action="{{ route('quality-monitoring.alerts.acknowledge', $alert) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs text-primary">
                                            Acknowledge
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted-foreground text-[11px]">Cleared</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted-foreground">
                                No quality alerts matching the specified criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-border">
            {{ $alerts->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection
