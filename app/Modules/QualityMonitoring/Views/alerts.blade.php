@extends('layouts.metronic.app')

@section('title', 'Quality Alerts')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Quality Monitoring</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Performance Alerts</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Threshold alerts, escalations, and acknowledgement tracking.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('quality-monitoring.index') }}" class="kt-btn kt-btn-outline">Back to Hub</a>
                    <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline">View Workplans</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header">
                <h3 class="kt-card-title">All Alerts</h3>
                <form method="GET" action="{{ route('quality-monitoring.alerts.index') }}" class="flex items-center gap-2">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search alerts…" class="kt-input min-w-[220px]" />
                    <select name="status" onchange="this.form.submit()" class="kt-select min-w-[140px]">
                        <option value="">All Statuses</option>
                        <option value="open" @selected($status === 'open')>Open</option>
                        <option value="acknowledged" @selected($status === 'acknowledged')>Acknowledged</option>
                        <option value="resolved" @selected($status === 'resolved')>Resolved</option>
                    </select>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Search</button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('quality-monitoring.alerts.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Clear</a>
                    @endif
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Type</th>
                                <th>Status</th>
                                <th>Workplan</th>
                                <th>KPI</th>
                                <th>Escalation</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($alerts as $alert)
                                <tr>
                                    <td><span class="kt-badge kt-badge-outline capitalize">{{ $alert->type }}</span></td>
                                    <td>
                                        @php
                                            $alertStatusClass = match($alert->status) {
                                                'open' => 'kt-badge-danger',
                                                'acknowledged' => 'kt-badge-warning',
                                                'resolved' => 'kt-badge-success',
                                                default => 'kt-badge-outline',
                                            };
                                        @endphp
                                        <span class="kt-badge {{ $alertStatusClass }}">{{ ucfirst($alert->status) }}</span>
                                    </td>
                                    <td>{{ $alert->workplan?->title ?: '—' }}</td>
                                    <td>{{ $alert->kpi?->name ?: '—' }}</td>
                                    <td>
                                        @if($alert->escalation_level > 0)
                                            <span class="kt-badge kt-badge-warning">Level {{ $alert->escalation_level }}</span>
                                        @else
                                            <span class="text-muted-foreground">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $alert->created_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="6">No alerts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $alerts->links() }}</div>
            </div>
        </div>
    </section>
@endsection
