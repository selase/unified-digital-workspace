@extends('layouts.metronic.app')

@section('title', 'Quality Monitoring & Accreditation Hub')

@section('content')
<div class="flex flex-col gap-5">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Quality Monitoring Hub"
        description="Quality Monitoring & Accreditation Hub: Institutional performance workplans, clinical KPIs, committee stage-gate reviews, and alert monitoring."
    >
        <x-slot:actions>
            <a href="{{ route('quality-monitoring.workplans.index') }}" class="kt-btn kt-btn-outline">
                Workplans Register
            </a>
            <a href="{{ route('quality-monitoring.alerts.index') }}" class="kt-btn kt-btn-outline">
                Quality Alerts
            </a>
            @can('qm.workplans.manage')
                <a href="{{ route('quality-monitoring.workplans.create') }}" class="kt-btn kt-btn-primary flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Quality Workplan
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Top Metrics Row: 5 Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Workplans</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['total_workplans'] }}</span>
                <span class="text-[11px] text-muted-foreground">All institutional</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Active Workplans</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-primary">{{ $metrics['active_workplans'] }}</span>
                <span class="text-[11px] text-muted-foreground">Under active audit</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Monitored KPIs</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold text-foreground">{{ $metrics['total_kpis'] }}</span>
                <span class="text-[11px] text-muted-foreground">Indicators</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pending Variances</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold {{ $metrics['pending_variances'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground' }}">
                    {{ $metrics['pending_variances'] }}
                </span>
                <span class="text-[11px] text-muted-foreground">CAPA required</span>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Open Alerts</span>
            <div class="mt-2 flex items-baseline justify-between">
                <span class="text-2xl font-bold {{ $metrics['open_alerts'] > 0 ? 'text-destructive' : 'text-foreground' }}">
                    {{ $metrics['open_alerts'] }}
                </span>
                <span class="text-[11px] text-muted-foreground">Threshold breaches</span>
            </div>
        </div>
    </div>

    {{-- Main Grid: Left Recent Workplans, Right Alerts & Standards --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left 8 cols: Recent Workplans --}}
        <div class="lg:col-span-8 flex flex-col gap-5">
            <x-metronic.card
                title="Performance Workplans & Strategic Initiatives"
                subtitle="Monitored quality programmes across clinical directorates and operational units"
            >
                <div class="overflow-x-auto">
                    <table class="kt-table table-auto kt-table-border w-full text-left">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground border-b border-border">
                                <th class="py-3 px-3">Workplan Title</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3">Audit Period</th>
                                <th class="py-3 px-3 text-center">Objectives</th>
                                <th class="py-3 px-3 text-center">Reviews</th>
                                <th class="py-3 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-xs">
                            @forelse($recentWorkplans as $wp)
                                <tr class="hover:bg-muted/30 transition">
                                    <td class="py-3 px-3">
                                        <p class="font-semibold text-foreground">{{ $wp->title }}</p>
                                        <p class="text-[11px] text-muted-foreground">
                                            Owner: {{ $wp->owner ? $wp->owner->first_name.' '.$wp->owner->last_name : 'Quality Directorate' }}
                                        </p>
                                    </td>
                                    <td class="py-3 px-3">
                                        @php
                                            $wpBadge = match($wp->status) {
                                                'active' => 'success',
                                                'approved' => 'success',
                                                'submitted' => 'primary',
                                                'draft' => 'outline',
                                                'rejected' => 'destructive',
                                                default => 'outline',
                                            };
                                        @endphp
                                        <x-metronic.badge :variant="$wpBadge" size="sm">
                                            {{ ucfirst($wp->status) }}
                                        </x-metronic.badge>
                                    </td>
                                    <td class="py-3 px-3 text-muted-foreground">
                                        {{ $wp->period_start?->format('M Y') }} – {{ $wp->period_end?->format('M Y') }}
                                    </td>
                                    <td class="py-3 px-3 text-center font-medium text-foreground">
                                        {{ $wp->objectives_count }}
                                    </td>
                                    <td class="py-3 px-3 text-center text-muted-foreground">
                                        {{ $wp->reviews_count }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <a href="{{ route('quality-monitoring.workplans.show', $wp) }}" class="kt-btn kt-btn-outline py-1 px-2.5 text-xs">
                                            Workspace
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        No active performance workplans recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 pt-3 border-t border-border flex justify-end">
                    <a href="{{ route('quality-monitoring.workplans.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        View All Workplans &rarr;
                    </a>
                </div>
            </x-metronic.card>
        </div>

        {{-- Right 4 cols: Quality Alerts & Accreditation Standards --}}
        <div class="lg:col-span-4 flex flex-col gap-5">
            {{-- Alert Watchlist --}}
            <x-metronic.card title="Open Quality Alerts" subtitle="Active deviations needing acknowledgment">
                <div class="space-y-3">
                    @forelse($recentAlerts as $alert)
                        <div class="p-3 rounded-xl border border-destructive/20 bg-destructive/5 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-xs text-destructive capitalize">
                                    {{ str_replace('_', ' ', $alert->type) }}
                                </span>
                                <x-metronic.badge variant="destructive" size="sm">
                                    {{ ucfirst($alert->status) }}
                                </x-metronic.badge>
                            </div>
                            <p class="text-xs text-foreground font-medium truncate">
                                {{ $alert->kpi?->name ?: $alert->workplan?->title }}
                            </p>
                            <div class="flex items-center justify-between pt-1">
                                <span class="text-[11px] text-muted-foreground">{{ $alert->created_at?->diffForHumans() }}</span>
                                <form action="{{ route('quality-monitoring.alerts.acknowledge', $alert) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-[11px] font-semibold text-primary hover:underline">
                                        Acknowledge
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-muted-foreground">
                            <svg class="h-8 w-8 mx-auto text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-xs">Zero threshold breaches active.</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-3 pt-2 border-t border-border text-right">
                    <a href="{{ route('quality-monitoring.alerts.index') }}" class="text-xs font-semibold text-primary hover:underline">
                        Alert Register &rarr;
                    </a>
                </div>
            </x-metronic.card>

            {{-- Institutional Accreditation Standard Callout --}}
            <div class="rounded-xl border border-border bg-card p-5 space-y-3">
                <div class="flex items-center gap-2 text-primary font-semibold text-xs uppercase tracking-wider">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    JCI / ISO Quality Assurance
                </div>
                <p class="text-xs text-muted-foreground leading-relaxed">
                    Quality Monitoring enforces multi-tier stage gate approvals, immutable measurement audit trails, and documented corrective actions for all clinical and organizational metrics.
                </p>
                <div class="pt-2 border-t border-border flex items-center justify-between text-xs text-muted-foreground">
                    <span>Module Version {{ $moduleVersion }}</span>
                    <span>{{ $featureCount }} Features Active</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
